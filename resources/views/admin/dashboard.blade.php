<x-layouts.admin title="Overview">
    <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm text-slate-500">Good {{ now('Europe/Zurich')->hour < 12 ? 'morning' : 'afternoon' }}, {{ str($user->name)->before(' ') }}.</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Here’s today’s operations picture.</p>
        </div>
        <a href="{{ route('admin.bookings.index', ['unseen' => 1]) }}" class="admin-button">Review new bookings</a>
    </div>

    <section class="flex flex-wrap gap-4">
        @foreach([['New bookings', $stats['new'], 'Unread customer bookings'], ['Pickups today', $stats['today'], 'Europe/Zurich']] as [$label, $value, $detail])
            <article class="admin-card w-full p-4 sm:w-[15rem]"><p class="text-sm font-medium text-slate-500">{{ $label }}</p><div class="mt-2 flex items-end justify-between gap-3"><p class="text-3xl font-bold tracking-tight text-slate-950">{{ $value }}</p><p class="pb-1 text-xs text-slate-400">{{ $detail }}</p></div></article>
        @endforeach
    </section>

    <section class="mt-7 admin-card overflow-hidden">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.14em] text-emerald-700">Financial overview</p>
                <h2 class="mt-1 font-bold text-slate-900">Captured revenue and settlement</h2>
                <p class="mt-1 text-xs text-slate-500">All-time totals from payments, completed refunds, and synced Stripe settlement records.</p>
            </div>
            <div class="flex items-center gap-3">
                <p class="text-xs text-slate-400">Calculated {{ \Carbon\CarbonImmutable::parse($financialOverview['calculated_at'])->setTimezone('Europe/Zurich')->format('d M, H:i') }} Zurich</p>
                <form method="POST" action="{{ route('admin.dashboard.financial.refresh') }}">
                    @csrf
                    <button class="admin-button-soft !px-3 !py-2">Refresh</button>
                </form>
            </div>
        </header>

        <div class="divide-y divide-slate-100">
            @foreach ($financialOverview['currencies'] as $currency => $totals)
                @php($stripe = $financialOverview['stripe'][$currency] ?? ['fees' => 0, 'net' => 0, 'settled_payment_count' => 0, 'pending_ledger_count' => 0])
                @php($onlineCustomerRevenue = $totals['methods'][\App\Enums\PaymentMethod::Online->value]['net'] ?? 0)
                @php($directPaymentNet = $totals['net_customer_revenue'] - $onlineCustomerRevenue)
                @php($netReceived = $directPaymentNet + $stripe['net'])
                <section class="p-5">
                    <div class="flex items-center justify-between gap-3"><h3 class="font-bold text-slate-900">{{ $currency }} customer payments</h3><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ $totals['successful_bookings'] }} captured bookings</span></div>

                    <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5"><p class="text-xs font-semibold text-slate-500">Gross collected</p><p class="mt-1 text-lg font-bold text-slate-950">{{ $currency }} {{ number_format($totals['gross_payments'] / 100, 2) }}</p><p class="mt-1 text-[11px] text-slate-400">All paid methods</p></div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5"><p class="text-xs font-semibold text-slate-500">Refunded</p><p class="mt-1 text-lg font-bold text-rose-700">{{ $currency }} {{ number_format($totals['total_refunded'] / 100, 2) }}</p><p class="mt-1 text-[11px] text-slate-400">Succeeded only</p></div>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3.5"><p class="text-xs font-semibold text-emerald-800">Net received</p><p class="mt-1 text-lg font-bold text-emerald-950">{{ $currency }} {{ number_format($netReceived / 100, 2) }}</p><p class="mt-1 text-[11px] text-emerald-700">After Stripe fees and refunds</p></div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5"><p class="text-xs font-semibold text-amber-800">COA balance due</p><p class="mt-1 text-lg font-bold text-amber-950">{{ $currency }} {{ number_format($totals['cash_on_arrival_due'] / 100, 2) }}</p><p class="mt-1 text-[11px] text-amber-700">{{ $totals['cash_on_arrival_count'] }} active booking(s)</p></div>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-[1.45fr_1fr]">
                        <div class="rounded-xl border border-slate-200">
                            <div class="border-b border-slate-100 px-4 py-3"><p class="text-sm font-bold text-slate-900">Payment method breakdown</p></div>
                            <div class="divide-y divide-slate-100">
                                @foreach (\App\Enums\PaymentMethod::cases() as $method)
                                    @php($methodTotals = $totals['methods'][$method->value] ?? ['gross' => 0, 'refunded' => 0, 'net' => 0, 'payment_count' => 0])
                                    @php($receivedAmount = $method === \App\Enums\PaymentMethod::Online ? $stripe['net'] : $methodTotals['net'])
                                    <div class="grid grid-cols-[1fr_auto] gap-x-4 px-4 py-3 text-sm sm:grid-cols-[1fr_auto_auto_auto]">
                                        <span class="font-semibold text-slate-700">{{ str($method->value)->replace('_', ' ')->title() }} <span class="ml-1 text-xs font-normal text-slate-400">{{ $methodTotals['payment_count'] }} paid</span></span>
                                        <span class="hidden text-slate-500 sm:block">Gross {{ $currency }} {{ number_format($methodTotals['gross'] / 100, 2) }}</span>
                                        <span class="hidden text-rose-600 sm:block">Refunded {{ $currency }} {{ number_format($methodTotals['refunded'] / 100, 2) }}</span>
                                        <span class="font-bold text-slate-900">Received {{ $currency }} {{ number_format($receivedAmount / 100, 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div><p class="text-sm font-bold text-slate-900">Stripe settlement</p><p class="mt-1 text-xs text-slate-500">Provider ledger</p></div>
                                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">Stripe</span>
                            </div>
                            <p class="mt-4 text-2xl font-bold tracking-tight text-slate-950">{{ $currency }} {{ number_format($stripe['net'] / 100, 2) }}</p>
                            <p class="mt-1 text-sm leading-5 text-slate-500">Actual balance movement after synced charges, fees, and refunds.</p>
                            <div class="mt-4 grid gap-3 border-t border-slate-100 pt-3 text-sm sm:grid-cols-2"><div><p class="text-xs font-medium text-slate-500">Settled payments</p><p class="mt-1 font-bold text-slate-900">{{ $stripe['settled_payment_count'] }}</p></div>@if ($stripe['pending_ledger_count'] > 0)<div><p class="text-xs font-medium text-amber-700">Ledger pending</p><p class="mt-1 font-bold text-amber-800">{{ $stripe['pending_ledger_count'] }}</p></div>@endif</div>
                        </div>
                    </div>
                </section>
            @endforeach

            @foreach ($financialOverview['stripe'] as $currency => $stripe)
                @continue(array_key_exists($currency, $financialOverview['currencies']))
                <section class="p-5"><div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-bold text-slate-900">Stripe settlement · {{ $currency }}</p><p class="mt-1 text-xs text-slate-500">Provider ledger</p></div><span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">Stripe</span></div><p class="mt-4 text-2xl font-bold tracking-tight text-slate-950">{{ $currency }} {{ number_format($stripe['net'] / 100, 2) }}</p><p class="mt-1 text-sm leading-5 text-slate-500">Shown separately because Stripe settled it in a different currency.</p></div></section>
            @endforeach
        </div>
    </section>

    <section class="mt-7 grid gap-7 xl:grid-cols-[1.5fr_1fr]">
        <article class="admin-card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="font-bold text-slate-900">Booking queue</h2><p class="mt-0.5 text-xs text-slate-500">Needs review and unseen confirmations first.</p></div><a class="text-sm font-semibold text-slate-700 hover:text-slate-950" href="{{ route('admin.bookings.index') }}">All bookings →</a></div>
            <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Booking</th><th>Pickup</th><th>Status</th><th></th></tr></thead><tbody>@forelse($recentBookings as $booking)<tr><td><p class="font-semibold text-slate-900">{{ $booking->reference }}</p><p class="mt-0.5 text-xs text-slate-400">{{ $booking->customer_name }}</p></td><td>{{ optional($booking->legs->first()?->starts_at)->setTimezone('Europe/Zurich')->format('d M, H:i') }}</td><td><x-admin.status :value="$booking->status->value" /></td><td><a class="font-semibold text-slate-600 hover:text-slate-950" href="{{ route('admin.bookings.show', $booking) }}">Open</a></td></tr>@empty<tr><td colspan="4" class="px-5 py-10 text-center text-sm text-slate-400">No bookings yet.</td></tr>@endforelse</tbody></table></div>
        </article>
        <aside class="space-y-5"><article class="admin-card p-5"><div class="flex items-center justify-between"><h2 class="font-bold text-slate-900">Payment status</h2><a href="{{ route('admin.payments.index') }}" class="text-sm font-semibold text-slate-600">View ledger</a></div><div class="mt-5 space-y-3">@forelse($paymentSummary as $status => $count)<div class="flex items-center justify-between"><x-admin.status :value="$status" /><span class="font-bold text-slate-900">{{ $count }}</span></div>@empty<p class="text-sm text-slate-400">No payment records yet.</p>@endforelse</div></article>
            {{-- <article class="rounded-2xl bg-slate-950 p-5 text-white"><p class="text-xs font-bold uppercase tracking-[.14em] text-amber-300">Fleet readiness</p><p class="mt-3 text-3xl font-bold">{{ $vehicleCount }}</p><p class="mt-1 text-sm text-slate-400">active vehicle types available in the catalogue.</p><a href="{{ route('admin.fleet.index') }}" class="mt-5 inline-block text-sm font-semibold text-white">Manage fleet →</a></article> --}}
        </aside>
    </section>
</x-layouts.admin>
