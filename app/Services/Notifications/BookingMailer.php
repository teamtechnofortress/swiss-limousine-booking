<?php

namespace App\Services\Notifications;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Settings\SettingsService;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/** Plain-text customer emails. Wording lives here until notification templates are built. */
final class BookingMailer
{
    public function __construct(private readonly SettingsService $settings) {}

    public function received(Booking $booking, string $accessToken, ?string $verificationToken): void
    {
        $manage = $this->url("/bookings/{$booking->reference}?token={$accessToken}");

        $lines = match ($booking->status) {
            BookingStatus::PendingVerification => [
                'Your booking is reserved, but not yet confirmed.',
                'Please confirm it by opening this link within '.$this->settings->int('coa_verification_ttl_hours').' hours:',
                $this->url("/bookings/verify?token={$verificationToken}"),
            ],
            BookingStatus::PendingPayment => [
                'Your booking is reserved. Complete the payment within '.$this->settings->int('hold_ttl_minutes').' minutes:',
                $manage,
            ],
            default => [
                'Your booking is confirmed.',
            ],
        };

        $this->send($booking, 'Booking '.$booking->reference, [
            "Hello {$booking->customer_name},",
            '',
            ...$lines,
            '',
            "Reference: {$booking->reference}",
            "Manage your booking: {$manage}",
            '',
            ...$this->summary($booking),
        ]);
    }

    public function confirmed(Booking $booking): void
    {
        $this->send($booking, 'Payment received: '.$booking->reference, [
            "Hello {$booking->customer_name},",
            '',
            "Your payment for booking {$booking->reference} has been received and your trip is confirmed.",
            '',
            ...$this->summary($booking),
        ]);
    }

    /** @return list<string> */
    private function summary(Booking $booking): array
    {
        $lines = [
            'Trip summary',
            ...$booking->legs->map(fn ($leg) => sprintf(
                '- %s: %s to %s, %s',
                ucfirst($leg->direction),
                $leg->pickup['address'] ?? '',
                $leg->dropoff['address'] ?? '',
                $leg->starts_at->setTimezone('Europe/Zurich')->format('d.m.Y H:i'),
            ))->all(),
            '',
            'Net: '.$this->money($booking->taxable_amount),
        ];

        if ($booking->vat_rate_bp > 0) {
            $lines[] = sprintf('VAT %s%%: %s', number_format($booking->vat_rate_bp / 100, 2), $this->money($booking->vat_amount));
        }

        $lines[] = 'Total: '.$this->money($booking->total_amount);

        if ($booking->vat_registration_number !== null) {
            $lines[] = 'VAT number: '.$booking->vat_registration_number;
        }

        $lines[] = '';
        $lines[] = $this->settings->string('company_legal_name');
        $lines[] = $this->settings->string('company_address');
        $lines[] = $this->settings->string('company_email');

        return array_values(array_filter($lines, fn ($line) => $line !== ''));
    }

    /** @param list<string> $lines */
    private function send(Booking $booking, string $subject, array $lines): void
    {
        $body = implode("\n", $lines);

        Mail::raw($body, function (Message $message) use ($booking, $subject): void {
            $message->to($booking->customer_email)->subject($subject);

            $replyTo = $this->settings->string('company_email');

            if ($replyTo !== '') {
                $message->replyTo($replyTo);
            }
        });
    }

    private function money(int $rappen): string
    {
        return $this->settings->string('currency').' '.number_format($rappen / 100, 2, '.', "'");
    }

    private function url(string $path): string
    {
        return rtrim((string) config('limo.frontend_url'), '/').$path;
    }
}
