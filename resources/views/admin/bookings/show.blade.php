<x-layouts.admin :title="$booking->reference">
    @php
        $currency = $booking->currency ?: 'CHF';
        $payment = $booking->payment;
        $isCashOnArrival = $payment?->method === \App\Enums\PaymentMethod::CashOnArrival;
        $isOfflinePayment = $payment && $payment->method !== \App\Enums\PaymentMethod::Online;
        $canCancel = $booking->status->canTransitionTo(\App\Enums\BookingStatus::Cancelled);
        $refunded = $payment ? (int) $payment->refunds->where('status', \App\Enums\RefundStatus::Succeeded)->sum('gross_amount') : 0;
        $reserved = $payment ? (int) $payment->refunds->whereIn('status', [\App\Enums\RefundStatus::Pending, \App\Enums\RefundStatus::RequiresAction])->sum('gross_amount') : 0;
        $availableToRefund = $payment ? max(0, $payment->gross_amount - $refunded - $reserved) : 0;
        $stripeFee = $payment?->provider_ledger_synced_at ? min((int) $payment->provider_fee_amount, $availableToRefund) : 0;
        $canRefund = $payment && in_array($payment->status, [\App\Enums\PaymentStatus::Paid, \App\Enums\PaymentStatus::PartiallyRefunded], true) && $availableToRefund > 0;
        $paymentVisualState = match ($payment?->status) {
            \App\Enums\PaymentStatus::Paid => 'is-paid',
            \App\Enums\PaymentStatus::Refunded, \App\Enums\PaymentStatus::PartiallyRefunded => 'is-refunded',
            \App\Enums\PaymentStatus::Failed => 'is-attention',
            default => 'is-pending',
        };
    @endphp

    <div class="booking-detail">
    <section class="booking-hero mb-6 flex flex-wrap items-start justify-between gap-5">
        <div class="relative z-10">
            <a href="{{ route('admin.bookings.index') }}" class="booking-back text-sm font-semibold">
                ← Back to bookings
            </a>

            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-bold tracking-tight text-white">{{ $booking->reference }}</h2>
                <x-admin.status :value="$booking->status->value" />
            </div>
            <p class="mt-1 text-sm text-slate-300">
                Booked {{ $booking->created_at?->setTimezone('Europe/Zurich')->format('d M Y · H:i') }} Zurich time
            </p>
        </div>

        <div class="booking-total relative z-10 text-left sm:text-right">
            <p class="text-xs font-bold uppercase tracking-[.13em] text-indigo-200">Booking total</p>
            <p class="mt-1 text-2xl font-bold text-white">{{ $currency }} {{ number_format($booking->total_amount / 100, 2) }}</p>
        </div>

        @if ($canCancel || $canRefund)
            <div class="relative z-10 flex w-full flex-wrap gap-2 border-t border-white/15 pt-4 sm:justify-end">
                @can('payments.refund')
                    @if ($canRefund)
                        <button type="button" data-refund-open="refund-modal-{{ $payment->id }}" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-rose-700">Refund payment</button>
                    @endif
                @endcan
                @can('bookings.cancel')
                    @if ($canCancel)
                        <button type="button" data-booking-cancel-open="booking-cancel-modal-{{ $booking->id }}" class="rounded-lg border border-white/30 bg-white/10 px-3 py-2 text-sm font-bold text-white transition hover:bg-white/20">Cancel booking</button>
                    @endif
                @endcan
            </div>
        @endif
    </section>

    <div class="booking-detail-grid grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(20rem,1fr)]">
        <div class="space-y-6">
            <x-admin.trip-booking-card :booking="$booking" />

            <article class="admin-card booking-panel booking-itinerary overflow-hidden">
                <div class="booking-panel-heading px-6 py-5">
                    <p class="text-xs font-bold uppercase tracking-[.13em] text-indigo-600">Itinerary</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900">Pickup and drop-off</h3>
                    <p class="mt-1 text-sm text-slate-500">Every booked leg, with its own scheduled time and route.</p>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse ($booking->legs as $leg)
                        <section class="booking-leg p-6">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-bold text-slate-900">{{ str($leg->direction)->title() }} journey</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $leg->starts_at->setTimezone('Europe/Zurich')->format('D, d M Y · H:i') }} Zurich time</p>
                                </div>
                                <span class="booking-leg-price">{{ $currency }} {{ number_format($leg->amount / 100, 2) }}</span>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="booking-stop booking-stop-pickup p-4">
                                    <p class="text-[11px] font-bold uppercase tracking-[.13em] text-emerald-700">Pickup</p>
                                    <p class="mt-2 text-sm font-semibold leading-6 text-slate-800">{{ $leg->pickup['address'] ?? 'Pickup address unavailable' }}</p>
                                </div>
                                <div class="booking-stop booking-stop-dropoff p-4">
                                    <p class="text-[11px] font-bold uppercase tracking-[.13em] text-rose-700">Drop-off</p>
                                    <p class="mt-2 text-sm font-semibold leading-6 text-slate-800">{{ $leg->dropoff['address'] ?? 'Drop-off address unavailable' }}</p>
                                </div>
                            </div>
                        </section>
                    @empty
                        <p class="p-6 text-sm text-slate-500">No journey legs are available for this booking.</p>
                    @endforelse
                </div>
            </article>

            <article class="admin-card booking-panel booking-payment {{ $paymentVisualState }} p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.13em] text-indigo-600">Payment</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-900">Payment and pricing</h3>
                    </div>
                    @if ($payment)
                        <x-admin.status :value="$payment->status->value" />
                    @endif
                </div>

                @if ($payment)
                    <div class="booking-payment-method mt-5 {{ $isCashOnArrival && $payment->status !== \App\Enums\PaymentStatus::Paid ? 'is-cash-due' : '' }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-800">{{ str($payment->method->value)->replace('_', ' ')->title() }}</p>
                                @if ($isCashOnArrival && $payment->status !== \App\Enums\PaymentStatus::Paid)
                                    <p class="mt-1 text-sm leading-6 text-amber-800">Collect {{ $currency }} {{ number_format($payment->gross_amount / 100, 2) }} from the customer at arrival, then record it here.</p>
                                @elseif ($payment->paid_at)
                                    <p class="mt-1 text-sm text-slate-500">Received {{ $payment->paid_at->setTimezone('Europe/Zurich')->format('d M Y · H:i') }} Zurich time</p>
                                @else
                                    <p class="mt-1 text-sm text-slate-500">{{ $payment->status === \App\Enums\PaymentStatus::Unpaid ? 'Payment has not yet been received.' : 'Payment is being processed.' }}</p>
                                @endif
                            </div>

                            @can ('payments.mark_paid')
                                @if ($isOfflinePayment && $payment->status !== \App\Enums\PaymentStatus::Paid)
                                    <form method="POST" action="{{ route('admin.bookings.mark-paid', $booking) }}">
                                        @csrf
                                        <button class="admin-button {{ $isCashOnArrival ? '!bg-amber-700 hover:!bg-amber-800' : '' }}">
                                            {{ $isCashOnArrival ? 'Record cash received' : 'Mark payment received' }}
                                        </button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </div>
                @else
                    <div class="booking-payment-method mt-5 text-sm text-slate-500">No payment record is attached to this booking.</div>
                @endif

                <dl class="mt-5 space-y-3 text-sm">
                    <div class="booking-price-row flex justify-between gap-5">
                        <dt class="text-slate-500">Net amount</dt>
                        <dd class="font-semibold text-slate-800">{{ $currency }} {{ number_format($booking->taxable_amount / 100, 2) }}</dd>
                    </div>
                    <div class="booking-price-row flex justify-between gap-5">
                        <dt class="text-slate-500">VAT ({{ number_format($booking->vat_rate_bp / 100, 2) }}%)</dt>
                        <dd class="font-semibold text-slate-800">{{ $currency }} {{ number_format($booking->vat_amount / 100, 2) }}</dd>
                    </div>
                    <div class="booking-price-row is-total flex justify-between gap-5 pt-3 text-base">
                        <dt class="font-bold text-slate-900">Total</dt>
                        <dd class="font-bold text-slate-900">{{ $currency }} {{ number_format($booking->total_amount / 100, 2) }}</dd>
                    </div>
                </dl>

                @if ($payment?->provider_ledger_synced_at)
                    <div class="mt-5 border-t border-slate-100 pt-5">
                        <p class="admin-label">Stripe settlement</p>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-5"><dt class="text-slate-500">Balance amount</dt><dd class="font-semibold text-slate-800">{{ $payment->provider_balance_currency }} {{ number_format($payment->provider_balance_amount / 100, 2) }}</dd></div>
                            <div class="flex justify-between gap-5"><dt class="text-slate-500">Stripe processing fee</dt><dd class="font-semibold text-slate-800">{{ $payment->provider_balance_currency }} {{ number_format($payment->provider_fee_amount / 100, 2) }}</dd></div>
                            <div class="flex justify-between gap-5"><dt class="text-slate-500">Stripe net</dt><dd class="font-semibold text-slate-800">{{ $payment->provider_balance_currency }} {{ number_format($payment->provider_net_amount / 100, 2) }}</dd></div>
                            @if ($payment->provider_exchange_rate)<div class="flex justify-between gap-5"><dt class="text-slate-500">Exchange rate</dt><dd class="font-semibold text-slate-800">{{ $payment->provider_exchange_rate }}</dd></div>@endif
                        </dl>
                    </div>
                @endif

                @if ($payment?->refunds->isNotEmpty())
                    <div class="mt-5 border-t border-slate-100 pt-5">
                        <p class="admin-label">Refunds</p>
                        <div class="mt-3 space-y-2">
                            @foreach ($payment->refunds as $refund)
                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span class="text-slate-600">{{ str($refund->status->value)->replace('_', ' ')->title() }}{{ $refund->reason ? ' · '.$refund->reason : '' }}</span>
                                    <span class="font-semibold text-slate-800">{{ $currency }} {{ number_format($refund->gross_amount / 100, 2) }}</span>
                                </div>
                                @if ($refund->cancel_booking_on_success)
                                    <p class="text-xs {{ $refund->booking_cancelled_at ? 'text-emerald-700' : 'text-slate-500' }}">{{ $refund->booking_cancelled_at ? 'Booking cancelled after Stripe confirmation.' : 'Booking will be cancelled after Stripe confirms this refund.' }}</p>
                                @endif
                                @if ($refund->provider_ledger_synced_at)
                                    <p class="text-xs text-slate-500">Stripe net: {{ $refund->provider_balance_currency }} {{ number_format($refund->provider_net_amount / 100, 2) }} · fee: {{ $refund->provider_balance_currency }} {{ number_format($refund->provider_fee_amount / 100, 2) }}</p>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </article>
        </div>

        <aside class="space-y-6">
            <article class="admin-card booking-panel booking-side-panel p-6">
                <p class="text-xs font-bold uppercase tracking-[.13em] text-indigo-600">Customer</p>
                <h3 class="mt-1 text-lg font-bold text-slate-900">Customer details</h3>

                <dl class="mt-5 space-y-4 text-sm">
                    <div>
                        <dt class="admin-label">Name</dt>
                        <dd class="mt-1.5 font-semibold text-slate-800">{{ $booking->customer_name }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">Email</dt>
                        <dd class="mt-1.5 break-words"><a class="font-semibold text-indigo-700 hover:text-indigo-900" href="mailto:{{ $booking->customer_email }}">{{ $booking->customer_email }}</a></dd>
                    </div>
                    <div>
                        <dt class="admin-label">Phone</dt>
                        <dd class="mt-1.5 font-semibold text-slate-800">{{ $booking->customer_phone ?: 'Not provided' }}</dd>
                    </div>
                    @if ($booking->flight_number)
                        <div>
                            <dt class="admin-label">Flight number</dt>
                            <dd class="mt-1.5 font-semibold text-slate-800">{{ $booking->flight_number }}</dd>
                        </div>
                    @endif
                </dl>
            </article>

            <article class="admin-card booking-panel booking-side-panel p-6">
                <p class="text-xs font-bold uppercase tracking-[.13em] text-indigo-600">Vehicle</p>
                <h3 class="mt-1 text-lg font-bold text-slate-900">Vehicle and capacity</h3>

                <div class="booking-vehicle-summary mt-5 p-4">
                    <p class="font-semibold text-slate-800">{{ $booking->vehicle?->name ?? 'Vehicle unavailable' }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $booking->passengers }} {{ str('passenger')->plural($booking->passengers) }} · {{ $booking->luggage }} {{ str('bag')->plural($booking->luggage) }}</p>
                </div>

                <div class="mt-4">
                    <p class="admin-label">Assigned fleet unit</p>
                    <p class="mt-1.5 font-semibold text-slate-800">{{ $booking->fleetUnit?->plate ?? 'Not assigned yet' }}</p>
                </div>

                @can ('bookings.assign')
                    @if ($units->isNotEmpty() && in_array($booking->status, [\App\Enums\BookingStatus::Confirmed, \App\Enums\BookingStatus::Assigned], true))
                        <form class="mt-5 border-t border-slate-100 pt-5" method="POST" action="{{ route('admin.bookings.assign', $booking) }}">
                            @csrf
                            <label class="admin-label" for="fleet_unit_id">Assign or change fleet unit</label>
                            <div class="mt-2 flex gap-2">
                                <select id="fleet_unit_id" name="fleet_unit_id" class="admin-input !mt-0" required>
                                    <option value="">Choose unit</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}" @selected($booking->fleet_unit_id === $unit->id)>{{ $unit->plate }}</option>
                                    @endforeach
                                </select>
                                <button class="admin-button">Save</button>
                            </div>
                        </form>
                    @endif
                @endcan
            </article>




        </aside>
    </div>

    @can('payments.refund')
        @if ($canRefund)
            @include('admin.payments._refund-modal', compact('payment', 'availableToRefund', 'reserved', 'stripeFee'))
        @endif
    @endcan

    @can('bookings.cancel')
        @if ($canCancel)
            <dialog id="booking-cancel-modal-{{ $booking->id }}" class="fixed left-1/2 top-1/2 m-0 w-[calc(100vw-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/55">
                <form method="POST" action="{{ route('admin.bookings.cancel-without-refund', $booking) }}">
                    @csrf
                    <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                        <div><p class="text-xs font-bold uppercase tracking-[.14em] text-rose-600">Cancel booking</p><h2 class="mt-1 text-lg font-bold text-slate-950">{{ $booking->reference }}</h2></div>
                        <button type="button" data-booking-cancel-close class="grid size-9 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Close cancellation dialog">×</button>
                    </header>
                    <div class="space-y-4 p-5">
                        <p class="text-sm text-slate-600">This releases the booking’s schedule blocks and does not send a payment refund.</p>
                        <div>
                            <label class="admin-label" for="booking-cancel-reason-{{ $booking->id }}">Cancellation reason</label>
                            <textarea id="booking-cancel-reason-{{ $booking->id }}" class="admin-input" required maxlength="500" name="reason" rows="3" placeholder="Reason for cancellation"></textarea>
                        </div>
                    </div>
                    <footer class="flex flex-col-reverse gap-3 border-t border-slate-200 px-5 py-3 sm:flex-row sm:justify-end">
                        <button type="button" data-booking-cancel-close class="admin-button-soft">Keep booking</button>
                        <button class="admin-button !bg-rose-700 hover:!bg-rose-800">Cancel booking</button>
                    </footer>
                </form>
            </dialog>
        @endif
    @endcan
    </div>
</x-layouts.admin>
