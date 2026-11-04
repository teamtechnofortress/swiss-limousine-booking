<?php

namespace App\Exceptions;

use App\Enums\BookingStatus;
use RuntimeException;

/** Domain errors that are safe to show to customers. Rendered by bootstrap/app.php with a stable code. */
final class BookingException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function quoteUnavailable(): self
    {
        return new self('quote_unavailable', 'This quote has expired or was already used. Please search again.', 410);
    }

    public static function vehicleNotInQuote(): self
    {
        return new self('vehicle_not_in_quote', 'This vehicle is not part of this quote.');
    }

    public static function vehicleUnavailable(): self
    {
        return new self('vehicle_unavailable', 'This vehicle cannot be booked online.', 409);
    }

    public static function slotUnavailable(): self
    {
        return new self('slot_unavailable', 'This vehicle is no longer available for this time. Please search again.', 409);
    }

    public static function noVehiclesAvailable(): self
    {
        return new self('no_vehicles_available', 'No vehicles are available for this trip.');
    }

    public static function tripBookingUnavailable(): self
    {
        return new self('trip_booking_unavailable', 'This trip is no longer available for online booking. Please send us an enquiry instead.', 409);
    }

    public static function unknownLocation(): self
    {
        return new self('unknown_location', 'Please choose your pickup and drop-off from the suggestions.');
    }

    public static function countryNotAllowed(): self
    {
        return new self('country_not_allowed', 'We do not currently serve trips to or from this country.');
    }

    public static function pickupNotAllowed(string $message = ''): self
    {
        return new self('pickup_not_allowed', $message !== '' ? $message : 'Please choose one of the available pickup locations.');
    }

    public static function requiredLocationMissing(string $name): self
    {
        return new self('required_location_missing', "Each trip must include {$name}.");
    }

    public static function leadTime(int $hours): self
    {
        return new self('lead_time', "Bookings must be made at least {$hours} hours in advance.");
    }

    public static function tooFarAhead(int $days): self
    {
        return new self('too_far_ahead', "Bookings can be made up to {$days} days in advance.");
    }

    public static function roundTripDisabled(): self
    {
        return new self('round_trip_disabled', 'Return trips are not available.');
    }

    public static function returnTooEarly(int $minutes): self
    {
        return new self('return_too_early', "The return must be at least {$minutes} minutes after the drop-off.");
    }

    public static function paymentMethodUnavailable(): self
    {
        return new self('payment_method_unavailable', 'This payment method is not available for this booking.');
    }

    public static function cashLimitExceeded(): self
    {
        return new self('cash_limit_exceeded', 'Cash on arrival is not available for bookings of this amount.');
    }

    public static function holdExpired(): self
    {
        return new self('hold_expired', 'This reservation has expired. Please search again.', 409);
    }

    public static function checkoutNotAvailable(): self
    {
        return new self('checkout_unavailable', 'Online payment is not available for this booking.', 409);
    }

    public static function invalidVerificationLink(): self
    {
        return new self('invalid_verification_link', 'This link is invalid or has already been used.', 404);
    }

    public static function notFound(): self
    {
        return new self('not_found', 'Booking not found.', 404);
    }

    public static function notCancellable(): self
    {
        return new self('not_cancellable', 'This booking can no longer be cancelled online.', 409);
    }

    public static function notRefundable(): self
    {
        return new self('not_refundable', 'This payment cannot be refunded.', 409);
    }

    public static function refundTooHigh(int $refundable): self
    {
        return new self('refund_too_high', sprintf('The maximum refundable amount is CHF %.2f.', $refundable / 100));
    }

    public static function unitMismatch(): self
    {
        return new self('unit_mismatch', 'This vehicle unit does not belong to the booked vehicle.');
    }

    public static function unitConflict(): self
    {
        return new self('unit_conflict', 'This vehicle unit is already booked for this time.', 409);
    }

    public static function waitingNotAllowed(): self
    {
        return new self('waiting_not_allowed', 'Waiting time can only be recorded for assigned trips.', 409);
    }

    public static function invalidTransition(BookingStatus $from, BookingStatus $to): self
    {
        return new self('invalid_transition', "A booking cannot move from {$from->value} to {$to->value}.", 409);
    }
}
