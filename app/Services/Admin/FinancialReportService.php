<?php

namespace App\Services\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\Cache;

/**
 * Database-first financial dashboard totals. Every value is an integer in its currency's minor unit.
 *
 * The configured Laravel cache is only a short-lived projection. It is never incremented or decremented.
 */
final class FinancialReportService
{
    private const CACHE_KEY = 'admin:financial-overview:v1';

    private const CACHE_TTL_SECONDS = 300;

    private const DEFAULT_CURRENCY = 'CHF';

    /** @return array<string, mixed> */
    public function overview(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => $this->calculate());
    }

    /** @return array<string, mixed> */
    public function refresh(): array
    {
        $this->forget();

        return $this->overview();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    private function calculate(): array
    {
        $capturedStatuses = [
            PaymentStatus::Paid->value,
            PaymentStatus::PartiallyRefunded->value,
            PaymentStatus::Refunded->value,
        ];
        $currencies = [];

        $captured = Payment::query()
            ->whereIn('status', $capturedStatuses)
            ->selectRaw('currency, method, COUNT(*) as payment_count, COALESCE(SUM(gross_amount), 0) as gross_amount')
            ->groupBy('currency', 'method')
            ->get();

        foreach ($captured as $row) {
            $currency = (string) $row->currency;
            $method = $row->method instanceof PaymentMethod ? $row->method->value : (string) $row->method;
            $this->currency($currencies, $currency)['methods'][$method] = [
                'gross' => (int) $row->gross_amount,
                'refunded' => 0,
                'net' => (int) $row->gross_amount,
                'payment_count' => (int) $row->payment_count,
            ];
        }

        $refunds = Refund::query()
            ->join('payments', 'payments.id', '=', 'refunds.payment_id')
            ->where('refunds.status', RefundStatus::Succeeded->value)
            ->selectRaw('payments.currency as currency, payments.method as method, COALESCE(SUM(refunds.gross_amount), 0) as refund_amount')
            ->groupBy('payments.currency', 'payments.method')
            ->get();

        foreach ($refunds as $row) {
            $currency = (string) $row->currency;
            $method = (string) $row->method;
            $bucket = &$this->currency($currencies, $currency);
            $bucket['methods'][$method] ??= ['gross' => 0, 'refunded' => 0, 'net' => 0, 'payment_count' => 0];
            $bucket['methods'][$method]['refunded'] += (int) $row->refund_amount;
            $bucket['methods'][$method]['net'] = $bucket['methods'][$method]['gross'] - $bucket['methods'][$method]['refunded'];
            unset($bucket);
        }

        $coaDue = Payment::query()
            ->where('method', PaymentMethod::CashOnArrival->value)
            ->whereIn('status', [PaymentStatus::Unpaid->value, PaymentStatus::Pending->value])
            ->whereHas('booking', fn ($query) => $query->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::Assigned->value,
                BookingStatus::InProgress->value,
            ]))
            ->selectRaw('currency, COALESCE(SUM(gross_amount), 0) as due_amount, COUNT(*) as booking_count')
            ->groupBy('currency')
            ->get();

        foreach ($coaDue as $row) {
            $bucket = &$this->currency($currencies, (string) $row->currency);
            $bucket['cash_on_arrival_due'] = (int) $row->due_amount;
            $bucket['cash_on_arrival_count'] = (int) $row->booking_count;
            unset($bucket);
        }

        foreach ($currencies as &$currency) {
            foreach ($currency['methods'] as $method) {
                $currency['gross_payments'] += $method['gross'];
                $currency['total_refunded'] += $method['refunded'];
                $currency['successful_bookings'] += $method['payment_count'];
            }
            $currency['net_customer_revenue'] = $currency['gross_payments'] - $currency['total_refunded'];
        }
        unset($currency);

        $stripe = $this->stripeSettlement();

        // Keep an empty Swiss-franc report useful on a brand-new installation.
        $this->currency($currencies, self::DEFAULT_CURRENCY);
        ksort($currencies);
        ksort($stripe);

        return [
            'currencies' => $currencies,
            'stripe' => $stripe,
            'calculated_at' => now('UTC')->toIso8601String(),
        ];
    }

    /** @return array<string, array{gross_payments:int,total_refunded:int,net_customer_revenue:int,successful_bookings:int,cash_on_arrival_due:int,cash_on_arrival_count:int,methods:array<string, array{gross:int,refunded:int,net:int,payment_count:int}>}> */
    private function &currency(array &$currencies, string $currency): array
    {
        $currency = strtoupper($currency ?: self::DEFAULT_CURRENCY);
        $currencies[$currency] ??= [
            'gross_payments' => 0,
            'total_refunded' => 0,
            'net_customer_revenue' => 0,
            'successful_bookings' => 0,
            'cash_on_arrival_due' => 0,
            'cash_on_arrival_count' => 0,
            'methods' => [],
        ];

        return $currencies[$currency];
    }

    /** @return array<string, array{fees:int,net:int,settled_payment_count:int,pending_ledger_count:int}> */
    private function stripeSettlement(): array
    {
        $stripe = [];
        $capturedStatuses = [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value, PaymentStatus::Refunded->value];

        $payments = Payment::query()
            ->where('method', PaymentMethod::Online->value)
            ->whereIn('status', $capturedStatuses)
            ->selectRaw('provider_balance_currency as currency, COALESCE(SUM(provider_fee_amount), 0) as fees, COALESCE(SUM(provider_net_amount), 0) as net, COUNT(*) as settled_count')
            ->whereNotNull('provider_ledger_synced_at')
            ->groupBy('provider_balance_currency')
            ->get();

        foreach ($payments as $row) {
            $currency = strtoupper((string) ($row->currency ?: self::DEFAULT_CURRENCY));
            $stripe[$currency] = [
                'fees' => (int) $row->fees,
                'net' => (int) $row->net,
                'settled_payment_count' => (int) $row->settled_count,
                'pending_ledger_count' => 0,
            ];
        }

        $refunds = Refund::query()
            ->join('payments', 'payments.id', '=', 'refunds.payment_id')
            ->where('payments.method', PaymentMethod::Online->value)
            ->where('refunds.status', RefundStatus::Succeeded->value)
            ->whereNotNull('refunds.provider_ledger_synced_at')
            ->selectRaw('refunds.provider_balance_currency as currency, COALESCE(SUM(refunds.provider_fee_amount), 0) as fees, COALESCE(SUM(refunds.provider_net_amount), 0) as net')
            ->groupBy('refunds.provider_balance_currency')
            ->get();

        foreach ($refunds as $row) {
            $currency = strtoupper((string) ($row->currency ?: self::DEFAULT_CURRENCY));
            $stripe[$currency] ??= ['fees' => 0, 'net' => 0, 'settled_payment_count' => 0, 'pending_ledger_count' => 0];
            $stripe[$currency]['fees'] += (int) $row->fees;
            $stripe[$currency]['net'] += (int) $row->net;
        }

        $unsettled = Payment::query()
            ->where('method', PaymentMethod::Online->value)
            ->whereIn('status', $capturedStatuses)
            ->whereNull('provider_ledger_synced_at')
            ->selectRaw('currency, COUNT(*) as count')
            ->groupBy('currency')
            ->get();

        foreach ($unsettled as $row) {
            $currency = strtoupper((string) $row->currency);
            $stripe[$currency] ??= ['fees' => 0, 'net' => 0, 'settled_payment_count' => 0, 'pending_ledger_count' => 0];
            $stripe[$currency]['pending_ledger_count'] += (int) $row->count;
        }

        return $stripe;
    }
}
