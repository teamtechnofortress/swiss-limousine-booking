<x-layouts.admin :title="$booking->reference">
    @php
        $currency = $booking->currency ?: 'CHF';
        $payment = $booking->payment;
        $isCashOnArrival = $payment?->method === \App\Enums\PaymentMethod::CashOnArrival;
        $isOfflinePayment = $payment && $payment->method !== \App\Enums\PaymentMethod::Online;
        $canCancel = $booking->status->canTransitionTo(\App\Enums\BookingStatus::Cancelled);
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

                @if ($payment?->refunds->isNotEmpty())
                    <div class="mt-5 border-t border-slate-100 pt-5">
                        <p class="admin-label">Refunds</p>
                        <div class="mt-3 space-y-2">
                            @foreach ($payment->refunds as $refund)
                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span class="text-slate-600">{{ str($refund->status)->replace('_', ' ')->title() }}{{ $refund->reason ? ' · '.$refund->reason : '' }}</span>
                                    <span class="font-semibold text-slate-800">{{ $currency }} {{ number_format($refund->gross_amount / 100, 2) }}</span>
                                </div>
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

            <article class="admin-card booking-panel booking-side-panel p-6">
                <p class="text-xs font-bold uppercase tracking-[.13em] text-indigo-600">Journey changes</p>
                <h3 class="mt-1 text-lg font-bold text-slate-900">Waiting time and adjustments</h3>

                @if ($booking->adjustments->isNotEmpty())
                    <div class="mt-5 space-y-3">
                        @foreach ($booking->adjustments as $adjustment)
                            <div class="booking-adjustment flex justify-between gap-4 p-3 text-sm">
                                <span class="text-slate-600">{{ str($adjustment->type)->title() }} · {{ $adjustment->minutes }} min</span>
                                <span class="shrink-0 font-semibold text-slate-800">{{ $currency }} {{ number_format($adjustment->gross_amount / 100, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-4 text-sm text-slate-500">No journey adjustments have been recorded.</p>
                @endif

                @can ('bookings.waiting')
                    <form class="mt-5 border-t border-slate-100 pt-5" method="POST" action="{{ route('admin.bookings.waiting', $booking) }}">
                        @csrf
                        <label class="admin-label" for="minutes">Add waiting time</label>
                        <div class="mt-2 flex gap-2">
                            <input id="minutes" class="admin-input !mt-0" type="number" min="1" name="minutes" placeholder="Minutes" required>
                            <button class="admin-button">Add</button>
                        </div>
                    </form>
                @endcan
            </article>

            @can ('bookings.cancel')
                @if ($canCancel)
                    <article class="booking-danger p-6">
                        <p class="text-xs font-bold uppercase tracking-[.13em] text-rose-700">Booking management</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-900">Cancel this booking</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Use only after confirming the cancellation and any refund amount with the customer.</p>

                        <details class="mt-4">
                            <summary class="cursor-pointer text-sm font-semibold text-rose-700 hover:text-rose-900">Start cancellation</summary>
                            <form class="mt-4 space-y-3" method="POST" action="{{ route('admin.bookings.cancel', $booking) }}">
                                @csrf
                                <div>
                                    <label class="admin-label" for="refund_amount">Refund amount ({{ $currency }})</label>
                                    <input id="refund_amount" class="admin-input !mt-1" name="refund_amount" type="number" min="0" step="0.01" value="0.00" inputmode="decimal" required>
                                </div>
                                <div>
                                    <label class="admin-label" for="cancellation_reason">Cancellation reason</label>
                                    <textarea id="cancellation_reason" class="admin-input !mt-1" name="reason" rows="3" required></textarea>
                                </div>
                                <button class="admin-button w-full !bg-rose-700 hover:!bg-rose-800">Cancel booking</button>
                            </form>
                        </details>
                    </article>
                @endif
            @endcan
        </aside>
    </div>
    </div>
</x-layouts.admin>
