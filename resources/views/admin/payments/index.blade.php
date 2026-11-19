<x-layouts.admin title="Payments">
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm text-slate-500">A traceable ledger for customer payments and refunds.</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <select name="status" class="admin-input !mt-0 !w-auto">
                <option value="">All statuses</option>
                @foreach(\App\Enums\PaymentStatus::cases() as $option)
                    <option value="{{ $option->value }}" @selected($status === $option->value)>{{ str($option->value)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
            <button class="admin-button">Filter</button>
        </form>
    </div>

    <div class="admin-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Payment</th><th>Booking</th><th>Method</th><th>Amount</th><th>Stripe fee</th><th>Stripe net</th><th>Refunded</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($payments as $payment)
                        @php($refunded = $payment->refunds->where('status', \App\Enums\RefundStatus::Succeeded)->sum('gross_amount'))
                        @php($reserved = $payment->refunds->whereIn('status', [\App\Enums\RefundStatus::Pending, \App\Enums\RefundStatus::RequiresAction])->sum('gross_amount'))
                        @php($availableToRefund = max(0, $payment->gross_amount - $refunded - $reserved))
                        <tr>
                            <td>
                                <p class="font-semibold text-slate-800">{{ $payment->provider_reference ?: 'Offline payment' }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $payment->created_at->setTimezone('Europe/Zurich')->format('d M Y, H:i') }}</p>
                            </td>
                            <td><a href="{{ route('admin.bookings.show', $payment->booking) }}" class="font-bold text-slate-800 hover:text-slate-500">{{ $payment->booking->reference }}</a><p class="mt-1 text-xs text-slate-400">{{ $payment->booking->customer_name }}</p></td>
                            <td>{{ str($payment->method->value)->replace('_', ' ')->title() }}</td>
                            <td class="font-semibold">{{ $payment->currency }} {{ number_format($payment->gross_amount / 100, 2) }}</td>
                            <td>{{ $payment->provider_ledger_synced_at ? $payment->provider_balance_currency.' '.number_format($payment->provider_fee_amount / 100, 2) : '—' }}</td>
                            <td>{{ $payment->provider_ledger_synced_at ? $payment->provider_balance_currency.' '.number_format($payment->provider_net_amount / 100, 2) : '—' }}</td>
                            <td>{{ $refunded ? $payment->currency.' '.number_format($refunded / 100, 2) : '—' }}</td>
                            <td><x-admin.status :value="$payment->status->value" /></td>
                            <td>
                                @can('payments.refund')
                                    @if(in_array($payment->status, [\App\Enums\PaymentStatus::Paid, \App\Enums\PaymentStatus::PartiallyRefunded], true) && $availableToRefund > 0)
                                        <button type="button" data-refund-open="refund-modal-{{ $payment->id }}" class="admin-button !bg-rose-700 hover:!bg-rose-800">Refund</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-6 py-16 text-center text-sm text-slate-400">No payments match this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-4">{{ $payments->links() }}</div>
    </div>

    @can('payments.refund')
        @foreach($payments as $payment)
            @php($refunded = (int) $payment->refunds->where('status', \App\Enums\RefundStatus::Succeeded)->sum('gross_amount'))
            @php($reserved = (int) $payment->refunds->whereIn('status', [\App\Enums\RefundStatus::Pending, \App\Enums\RefundStatus::RequiresAction])->sum('gross_amount'))
            @php($availableToRefund = max(0, $payment->gross_amount - $refunded - $reserved))
            @php($stripeFee = $payment->provider_ledger_synced_at ? min((int) $payment->provider_fee_amount, $availableToRefund) : 0)
            @if(in_array($payment->status, [\App\Enums\PaymentStatus::Paid, \App\Enums\PaymentStatus::PartiallyRefunded], true) && $availableToRefund > 0)
                @include('admin.payments._refund-modal', compact('payment', 'availableToRefund', 'reserved', 'stripeFee'))
            @endif
        @endforeach
    @endcan
</x-layouts.admin>
