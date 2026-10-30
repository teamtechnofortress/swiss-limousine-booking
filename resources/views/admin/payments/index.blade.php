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
                <thead><tr><th>Payment</th><th>Booking</th><th>Method</th><th>Amount</th><th>Refunded</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($payments as $payment)
                        @php($refunded = $payment->refunds->where('status', 'succeeded')->sum('gross_amount'))
                        <tr>
                            <td>
                                <p class="font-semibold text-slate-800">{{ $payment->provider_reference ?: 'Offline payment' }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $payment->created_at->setTimezone('Europe/Zurich')->format('d M Y, H:i') }}</p>
                            </td>
                            <td><a href="{{ route('admin.bookings.show', $payment->booking) }}" class="font-bold text-slate-800 hover:text-slate-500">{{ $payment->booking->reference }}</a><p class="mt-1 text-xs text-slate-400">{{ $payment->booking->customer_name }}</p></td>
                            <td>{{ str($payment->method->value)->replace('_', ' ')->title() }}</td>
                            <td class="font-semibold">CHF {{ number_format($payment->gross_amount / 100, 2) }}</td>
                            <td>{{ $refunded ? 'CHF '.number_format($refunded / 100, 2) : '—' }}</td>
                            <td><x-admin.status :value="$payment->status->value" /></td>
                            <td>
                                @can('payments.refund')
                                    @if(in_array($payment->status, [\App\Enums\PaymentStatus::Paid, \App\Enums\PaymentStatus::PartiallyRefunded], true))
                                        <details class="relative">
                                            <summary class="cursor-pointer whitespace-nowrap font-semibold text-rose-700">Refund</summary>
                                            <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" class="absolute right-0 z-10 mt-2 w-72 space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-xl">
                                                @csrf
                                                <div><label class="admin-label">Amount (rappen)</label><input class="admin-input" required min="1" max="{{ max(0, $payment->gross_amount - $refunded) }}" name="gross_amount" type="number" value="{{ max(0, $payment->gross_amount - $refunded) }}"></div>
                                                <div><label class="admin-label">Reason</label><textarea class="admin-input" required maxlength="500" name="reason" rows="3" placeholder="Reason shown in the audit trail"></textarea></div>
                                                <button class="admin-button w-full !bg-rose-700 hover:!bg-rose-800">Record refund</button>
                                            </form>
                                        </details>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-16 text-center text-sm text-slate-400">No payments match this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-4">{{ $payments->links() }}</div>
    </div>
</x-layouts.admin>
