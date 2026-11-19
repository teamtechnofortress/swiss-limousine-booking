<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingAdjustment;
use App\Models\BookingExtra;
use App\Models\BookingLeg;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Models\ScheduleBlock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * Explicit local/test reset for operational booking data.
 *
 * This is deliberately not called by DatabaseSeeder: it permanently removes booking,
 * payment, refund, payment-attempt, availability-block, and payment-webhook records.
 */
final class ClearBookingDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('ClearBookingDataSeeder may only run in the local or testing environment.');
        }

        $deleted = DB::transaction(function (): array {
            // Refunds and payment attempts have restrictive foreign keys to payments/bookings.
            // Delete every dependent table explicitly so the final booking deletion is a hard reset,
            // not a soft delete that leaves connected operational records behind.
            $counts = [
                'refunds' => DB::table('refunds')->delete(),
                'payment_attempts' => DB::table('payment_attempts')->delete(),
                'payments' => DB::table('payments')->delete(),
                'booking_extras' => DB::table('booking_extras')->delete(),
                'booking_adjustments' => DB::table('booking_adjustments')->delete(),
                'schedule_blocks' => DB::table('schedule_blocks')->whereNotNull('booking_id')->delete(),
                'booking_legs' => DB::table('booking_legs')->delete(),
                'bookings' => DB::table('bookings')->delete(),
            ];

            // Payment webhooks are not foreign-keyed, but retaining them could replay an old Stripe
            // event into the fresh ledger. These are the only payment-provider events in this app.
            $counts['webhook_events'] = DB::table('webhook_events')->delete();

            // Remove outstanding/failed jobs that reference records which were just erased, while
            // leaving unrelated email and application jobs intact.
            if (Schema::hasTable('jobs')) {
                $counts['queued_payment_jobs'] = DB::table('jobs')
                    ->where('payload', 'like', '%ProcessPaymentWebhook%')
                    ->orWhere('payload', 'like', '%SubmitStripeRefund%')
                    ->delete();
            }

            if (Schema::hasTable('failed_jobs')) {
                $counts['failed_payment_jobs'] = DB::table('failed_jobs')
                    ->where('payload', 'like', '%ProcessPaymentWebhook%')
                    ->orWhere('payload', 'like', '%SubmitStripeRefund%')
                    ->delete();
            }

            // Spatie's activity log is polymorphic and has no FK. Remove records whose subject was
            // removed, but preserve audit history for users, fleet, settings, trips, and enquiries.
            if (Schema::hasTable('activity_log')) {
                $counts['booking_activity'] = DB::table('activity_log')
                    ->whereIn('subject_type', [
                        Booking::class,
                        BookingLeg::class,
                        BookingExtra::class,
                        BookingAdjustment::class,
                        ScheduleBlock::class,
                        Payment::class,
                        PaymentAttempt::class,
                        Refund::class,
                    ])
                    ->delete();
            }

            return $counts;
        });

        $summary = collect($deleted)
            ->map(fn (int $count, string $table): string => "{$table}: {$count}")
            ->implode(', ');

        $this->command?->info("Booking reset complete ({$summary}).");
    }
}
