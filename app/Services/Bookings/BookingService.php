<?php

namespace App\Services\Bookings;

use App\Data\CheckoutSession;
use App\Data\CreateBookingData;
use App\Data\CreatedBooking;
use App\Enums\BlockType;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\BookingConfirmed;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingLeg;
use App\Models\Vehicle;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\VehicleRepositoryInterface;
use App\Services\Availability\AvailabilityService;
use App\Services\Payments\PaymentService;
use App\Services\Quotes\QuoteStore;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Customer-facing booking lifecycle: create, pay, verify, expire, and cancellation requests. */
final class BookingService
{
    public function __construct(
        private readonly QuoteStore $quotes,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly BookingRepositoryInterface $bookings,
        private readonly AvailabilityService $availability,
        private readonly BookingLocker $locker,
        private readonly PaymentService $payments,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Consumes the quote, takes the slot and saves the booking in one transaction.
     * The browser sends only the token and the chosen vehicle. Price and VAT come from the server snapshot.
     */
    public function create(CreateBookingData $data): CreatedBooking
    {
        $snapshot = $this->quotes->consume($data->quoteToken) ?? throw BookingException::quoteUnavailable();
        $offer = $snapshot->offerFor($data->vehicleId);
        $total = (int) $offer['vat']['gross'];

        $this->assertPaymentMethodAllowed($data->paymentMethod, $total);

        $now = CarbonImmutable::now('UTC');
        [$status, $blockType, $holdUntil, $verificationToken] = $this->initialState($data->paymentMethod, $now);
        $accessToken = Str::random(64);

        $booking = DB::transaction(function () use (
            $data, $snapshot, $offer, $total, $status, $blockType, $holdUntil, $verificationToken, $accessToken,
        ): Booking {
            $vehicle = $this->vehicles->lockVehicle($data->vehicleId);
            $windows = $this->legWindows($snapshot->legs);
            $this->availability->assertWindowsFree($vehicle, $windows);

            $booking = $this->bookings->create([
                'reference' => $this->newReference(),
                'status' => $status,
                'source' => 'website',
                'payment_method' => $data->paymentMethod,
                'vehicle_id' => $vehicle->id,
                'customer_name' => $data->customerName,
                'customer_email' => $data->customerEmail,
                'customer_phone' => $data->customerPhone,
                'passengers' => $snapshot->passengers,
                'luggage' => $snapshot->luggage,
                'flight_number' => $data->flightNumber,
                'currency' => $snapshot->currency,
                'taxable_amount' => $offer['vat']['net'],
                'vat_rate_bp' => $offer['vat']['rate_bp'],
                'vat_amount' => $offer['vat']['vat'],
                'total_amount' => $total,
                'vat_included' => $offer['vat']['included'],
                'vat_registration_number' => $this->settings->string('vat_registration_number') ?: null,
                'price_breakdown' => $offer['price'],
                'access_token_hash' => hash('sha256', $accessToken),
                'verification_token_hash' => $verificationToken !== null ? hash('sha256', $verificationToken) : null,
                'hold_expires_at' => $holdUntil,
            ]);

            foreach ($snapshot->legs as $index => $leg) {
                $bookingLeg = $booking->legs()->create([
                    'direction' => $leg['direction'],
                    'pickup' => $leg['pickup'],
                    'dropoff' => $leg['dropoff'],
                    'starts_at' => CarbonImmutable::parse($leg['starts_at']),
                    'distance_m' => $leg['distance_m'],
                    'duration_s' => $leg['duration_s'],
                    'amount' => $offer['price']['legs'][$leg['direction']],
                ]);

                // Slot-check-off vehicles never get blocks.
                if ($vehicle->slot_check_enabled) {
                    $this->availability->writeBlock(
                        $vehicle->id,
                        $booking->id,
                        $bookingLeg->id,
                        $blockType,
                        $windows[$index]['from'],
                        $windows[$index]['to'],
                        $holdUntil,
                    );
                }
            }

            $booking->payment()->create([
                'method' => $data->paymentMethod,
                'provider' => null,
                'gross_amount' => $total,
                'vat_amount' => $offer['vat']['vat'],
                'currency' => $snapshot->currency,
                'status' => PaymentStatus::Unpaid,
            ]);

            return $booking->load(['legs', 'payment', 'vehicle']);
        });

        return new CreatedBooking($booking, $accessToken, $verificationToken);
    }

    /**
     * Starts or restarts an online payment. The hold is renewed first, because a Stripe session
     * needs more than 30 minutes and a returning customer may have less time left on the original hold.
     */
    public function startOnlineCheckout(int $bookingId): CheckoutSession
    {
        $booking = DB::transaction(function () use ($bookingId): Booking {
            [, $booking] = $this->locker->lock($bookingId);

            if ($booking->payment_method !== PaymentMethod::Online || $booking->status !== BookingStatus::PendingPayment) {
                throw BookingException::checkoutNotAvailable();
            }

            if ($booking->hold_expires_at === null || $booking->hold_expires_at->isPast()) {
                throw BookingException::holdExpired();
            }

            $expiresAt = CarbonImmutable::now('UTC')->addMinutes($this->settings->int('hold_ttl_minutes'));
            $booking->blocks()->update(['expires_at' => $expiresAt]);
            $booking->forceFill(['hold_expires_at' => $expiresAt])->save();

            return $booking;
        });

        return $this->payments->createCheckout($booking, CarbonImmutable::instance($booking->hold_expires_at));
    }

    /** Finds a booking by reference and access token. Both must match. */
    public function findForCustomer(string $reference, string $plainToken): Booking
    {
        $booking = $this->bookings->findByReference($reference);

        if ($booking === null || $plainToken === '' || ! hash_equals((string) $booking->access_token_hash, hash('sha256', $plainToken))) {
            throw BookingException::notFound();
        }

        return $booking;
    }

    /** The customer clicked the cash verification link from their email. */
    public function verifyCashOnArrival(string $plainToken): Booking
    {
        $hash = hash('sha256', $plainToken);
        $candidate = $this->bookings->findByVerificationHash($hash)
            ?? throw BookingException::invalidVerificationLink();

        return DB::transaction(function () use ($candidate, $hash): Booking {
            [$vehicle, $booking] = $this->locker->lock($candidate->id);

            if ($booking->verification_token_hash === null) {
                return $booking; // already verified: the link is idempotent
            }

            if (! hash_equals((string) $booking->verification_token_hash, $hash)) {
                throw BookingException::invalidVerificationLink();
            }

            if (! in_array($booking->status, [BookingStatus::PendingVerification, BookingStatus::Expired], true)) {
                throw BookingException::invalidVerificationLink();
            }

            $this->settle($booking, $vehicle, paid: false);
            $booking->forceFill(['verification_token_hash' => null])->save();

            return $booking;
        });
    }

    /** Called by the webhook handler when Stripe reports a paid checkout. Safe to call twice. */
    public function confirmOnlinePayment(int $bookingId, string $sessionId, ?string $paymentIntent): void
    {
        DB::transaction(function () use ($bookingId, $sessionId, $paymentIntent): void {
            [$vehicle, $booking] = $this->locker->lock($bookingId);

            if (in_array($booking->status, [
                BookingStatus::Confirmed, BookingStatus::Assigned, BookingStatus::InProgress, BookingStatus::Completed,
            ], true)) {
                // Duplicate delivery. Record the payment reference only if it is missing.
                return;
            }

            $this->payments->recordPaid($booking, $sessionId, $paymentIntent);

            if (! in_array($booking->status, [BookingStatus::PendingPayment, BookingStatus::Expired], true)) {
                // Money arrived for a cancelled or review-state booking. It is recorded; an admin decides on a refund.
                Log::critical('Payment received for a booking that cannot be confirmed', [
                    'reference' => $booking->reference,
                    'status' => $booking->status->value,
                ]);

                return;
            }

            $this->settle($booking, $vehicle, paid: true);
        });
    }

    /** Stripe reported the checkout session expired. Only applies if that session is still the current one. */
    public function expireOnlineCheckout(int $bookingId, string $sessionId): void
    {
        DB::transaction(function () use ($bookingId, $sessionId): void {
            [, $booking] = $this->locker->lock($bookingId);

            if ($booking->status !== BookingStatus::PendingPayment) {
                return;
            }

            if ($booking->payment?->provider_reference !== $sessionId) {
                return; // a newer session replaced this one
            }

            $this->payments->markFailed($booking);
            $this->expireBooking($booking);
        });
    }

    /** Releases holds that lapsed without payment or verification. Runs every minute from the scheduler. */
    public function expireStale(int $limit = 200): int
    {
        $expired = 0;

        foreach ($this->bookings->expirationCandidates(CarbonImmutable::now('UTC'), $limit) as $candidate) {
            $expired += DB::transaction(function () use ($candidate): int {
                [, $booking] = $this->locker->lock($candidate->id);

                $open = in_array($booking->status, [BookingStatus::PendingPayment, BookingStatus::PendingVerification], true);
                $lapsed = $booking->hold_expires_at !== null && $booking->hold_expires_at->isPast();

                if (! $open || ! $lapsed) {
                    return 0;
                }

                $this->expireBooking($booking);

                return 1;
            });
        }

        return $expired;
    }

    /** The customer asks to cancel. The admin decides. Nothing is refunded here. */
    public function requestCancellation(Booking $booking, string $reason): void
    {
        if (! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Assigned], true)) {
            throw BookingException::notCancellable();
        }

        $booking->forceFill([
            'cancellation_requested_at' => CarbonImmutable::now('UTC'),
            'cancellation_reason' => $reason,
        ])->save();
    }

    /**
     * Moves a paid, verified or late-paid booking to confirmed.
     * A valid hold becomes a booking block. An expired hold is rechecked against free units.
     * If the slot is gone the booking goes to needs_review. Nothing is refunded automatically.
     */
    private function settle(Booking $booking, Vehicle $vehicle, bool $paid): void
    {
        $now = CarbonImmutable::now('UTC');

        $holdIsValid = $booking->status !== BookingStatus::Expired
            && $booking->hold_expires_at !== null
            && $booking->hold_expires_at->isFuture();

        if ($holdIsValid) {
            if ($vehicle->slot_check_enabled) {
                $booking->blocks()->update([
                    'block_type' => BlockType::Booking->value,
                    'expires_at' => null,
                ]);
            }
        } else {
            if ($booking->status !== BookingStatus::Expired) {
                $booking->transitionTo(BookingStatus::Expired);
            }

            $booking->blocks()->delete();

            $windows = $booking->legs->map(
                fn (BookingLeg $leg) => $this->availability->occupiedWindow($leg->starts_at, $leg->duration_s),
            )->values()->all();

            if (! $this->availability->windowsFree($vehicle, $windows)) {
                $booking->transitionTo(BookingStatus::NeedsReview);
                $booking->save();

                Log::warning('Booking needs review: the slot was taken after the hold ended', [
                    'reference' => $booking->reference,
                ]);

                return;
            }

            if ($vehicle->slot_check_enabled) {
                foreach ($booking->legs->values() as $index => $leg) {
                    $this->availability->writeBlock(
                        $vehicle->id,
                        $booking->id,
                        $leg->id,
                        BlockType::Booking,
                        $windows[$index]['from'],
                        $windows[$index]['to'],
                        null,
                    );
                }
            }
        }

        $booking->transitionTo(BookingStatus::Confirmed);
        $booking->forceFill([
            'hold_expires_at' => null,
            'paid_at' => $paid ? $now : $booking->paid_at,
            'verified_at' => $paid ? $booking->verified_at : $now,
        ])->save();

        $bookingId = $booking->id;
        DB::afterCommit(fn () => event(new BookingConfirmed($bookingId)));
    }

    private function expireBooking(Booking $booking): void
    {
        $booking->blocks()->delete();
        $booking->transitionTo(BookingStatus::Expired);
        $booking->save();
    }

    /** @return array{0: BookingStatus, 1: BlockType, 2: ?CarbonImmutable, 3: ?string} */
    private function initialState(PaymentMethod $method, CarbonImmutable $now): array
    {
        if ($method === PaymentMethod::Online) {
            return [
                BookingStatus::PendingPayment,
                BlockType::Hold,
                $now->addMinutes($this->settings->int('hold_ttl_minutes')),
                null,
            ];
        }

        if ($this->settings->bool('coa_email_verification_enabled')) {
            return [
                BookingStatus::PendingVerification,
                BlockType::Hold,
                $now->addHours($this->settings->int('coa_verification_ttl_hours')),
                Str::random(64),
            ];
        }

        return [BookingStatus::Confirmed, BlockType::Booking, null, null];
    }

    private function assertPaymentMethodAllowed(PaymentMethod $method, int $total): void
    {
        if (! $method->isCustomerFacing()) {
            throw BookingException::paymentMethodUnavailable();
        }

        if ($method === PaymentMethod::Online && ! $this->settings->bool('online_payment_enabled')) {
            throw BookingException::paymentMethodUnavailable();
        }

        if ($method === PaymentMethod::CashOnArrival) {
            if (! $this->settings->bool('cash_on_arrival_enabled')) {
                throw BookingException::paymentMethodUnavailable();
            }

            $limit = $this->settings->nullableInt('coa_max_amount');

            if ($limit !== null && $total > $limit) {
                throw BookingException::cashLimitExceeded();
            }
        }
    }

    /** @param list<array<string, mixed>> $legs @return list<array{from: CarbonImmutable, to: CarbonImmutable}> */
    private function legWindows(array $legs): array
    {
        return array_map(
            fn (array $leg) => $this->availability->occupiedWindow(
                CarbonImmutable::parse($leg['starts_at']),
                (int) $leg['duration_s'],
            ),
            $legs,
        );
    }

    private function newReference(): string
    {
        do {
            $reference = 'LX-'.Str::upper(Str::random(5));
        } while ($this->bookings->referenceExists($reference));

        return $reference;
    }
}
