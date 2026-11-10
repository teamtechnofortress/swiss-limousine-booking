<x-layouts.admin :title="$booking->reference">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.bookings.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">
                ← Back to bookings
            </a>

            <div class="mt-3 flex items-center gap-3">
                <h2 class="text-2xl font-bold tracking-tight text-slate-950">{{ $booking->reference }}</h2>
                <x-admin.status :value="$booking->status->value" />
            </div>
        </div>

        <div class="text-right">
            <p class="text-xs font-bold uppercase tracking-[.13em] text-slate-400">Booking total</p>
            <p class="mt-1 text-2xl font-bold text-slate-950">CHF {{ number_format($booking->total_amount / 100, 2) }}</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.55fr_1fr]">
        <div class="space-y-6">
            <article class="admin-card p-6">
                <h3 class="text-base font-bold text-slate-900">Customer &amp; journey</h3>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <p class="admin-label">Customer</p>
                        <p class="mt-2 font-semibold text-slate-800">{{ $booking->customer_name }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $booking->customer_email }}<br>
                            {{ $booking->customer_phone }}
                        </p>
                    </div>

                    <div>
                        <p class="admin-label">Vehicle</p>
                        <p class="mt-2 font-semibold text-slate-800">{{ $booking->vehicle?->name }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $booking->passengers }} passengers · {{ $booking->luggage }} bags
                        </p>
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    @foreach ($booking->legs as $leg)
                        <div class="rounded-xl bg-slate-50 p-4">
                            <div class="flex justify-between gap-4">
                                <p class="font-semibold text-slate-800">
                                    {{ str($leg->direction)->title() }} ·
                                    {{ $leg->starts_at->setTimezone('Europe/Zurich')->format('D, d M Y · H:i') }}
                                </p>
                                <span class="text-sm font-semibold text-slate-600">CHF {{ number_format($leg->amount / 100, 2) }}</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600">
                                {{ $leg->pickup['address'] ?? '—' }}
                                <span class="px-1.5 text-slate-400">→</span>
                                {{ $leg->dropoff['address'] ?? '—' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="admin-card p-6">
                <h3 class="font-bold text-slate-900">Financial summary</h3>

                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Net</dt>
                        <dd class="font-semibold">CHF {{ number_format($booking->taxable_amount / 100, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">VAT ({{ number_format($booking->vat_rate_bp / 100, 2) }}%)</dt>
                        <dd class="font-semibold">CHF {{ number_format($booking->vat_amount / 100, 2) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-slate-100 pt-3 text-base">
                        <dt class="font-bold">Total</dt>
                        <dd class="font-bold">CHF {{ number_format($booking->total_amount / 100, 2) }}</dd>
                    </div>
                </dl>

                @if ($booking->payment)
                    <div class="mt-5 rounded-xl bg-slate-50 p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500">Payment</span>
                            <x-admin.status :value="$booking->payment->status->value" />
                        </div>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ str($booking->payment->method->value)->replace('_', ' ')->title() }}
                        </p>
                    </div>
                @endif
            </article>
        </div>

        <aside class="space-y-6">
            <article class="admin-card p-5">
                <h3 class="font-bold text-slate-900">Operations</h3>

                @can ('bookings.assign')
                    @if ($units->isNotEmpty() && in_array($booking->status, [\App\Enums\BookingStatus::Confirmed, \App\Enums\BookingStatus::Assigned], true))
                        <form class="mt-4" method="POST" action="{{ route('admin.bookings.assign', $booking) }}">
                            @csrf
                            <label class="admin-label" for="fleet_unit_id">Assign fleet unit</label>
                            <div class="mt-2 flex gap-2">
                                <select id="fleet_unit_id" name="fleet_unit_id" class="admin-input !mt-0" required>
                                    <option value="">Choose unit</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}" @selected($booking->fleet_unit_id === $unit->id)>
                                            {{ $unit->plate }}
                                        </option>
                                    @endforeach
                                </select>
                                <button class="admin-button">Assign</button>
                            </div>
                        </form>
                    @endif
                @endcan

                @can ('payments.mark_paid')
                    @if ($booking->payment && $booking->payment->method !== \App\Enums\PaymentMethod::Online && $booking->payment->status !== \App\Enums\PaymentStatus::Paid)
                        <form class="mt-4 border-t border-slate-100 pt-4" method="POST" action="{{ route('admin.bookings.mark-paid', $booking) }}">
                            @csrf
                            <button class="admin-button w-full">Mark offline payment paid</button>
                        </form>
                    @endif
                @endcan

                @can ('bookings.waiting')
                    <form class="mt-4 border-t border-slate-100 pt-4" method="POST" action="{{ route('admin.bookings.waiting', $booking) }}">
                        @csrf
                        <label class="admin-label" for="minutes">Waiting time</label>
                        <div class="mt-2 flex gap-2">
                            <input id="minutes" class="admin-input !mt-0" type="number" min="1" name="minutes" placeholder="Minutes">
                            <button class="admin-button">Add</button>
                        </div>
                    </form>
                @endcan

                @can ('bookings.cancel')
                    @if ($booking->status->canTransitionTo(\App\Enums\BookingStatus::Cancelled))
                        <details class="mt-4 border-t border-slate-100 pt-4">
                            <summary class="cursor-pointer text-sm font-semibold text-rose-700">Cancel booking</summary>
                            <form class="mt-3 space-y-3" method="POST" action="{{ route('admin.bookings.cancel', $booking) }}">
                                @csrf
                                <input class="admin-input !mt-0" name="refund_gross" type="number" min="0" value="0" placeholder="Refund in rappen">
                                <textarea class="admin-input !mt-0" name="reason" required placeholder="Cancellation reason"></textarea>
                                <button class="admin-button w-full !bg-rose-700 hover:!bg-rose-800">Cancel booking</button>
                            </form>
                        </details>
                    @endif
                @endcan
            </article>

            @if ($booking->adjustments->isNotEmpty())
                <article class="admin-card p-5">
                    <h3 class="font-bold">Adjustments</h3>

                    <div class="mt-4 space-y-3">
                        @foreach ($booking->adjustments as $adjustment)
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-600">
                                    {{ str($adjustment->type)->title() }} · {{ $adjustment->minutes }} min
                                </span>
                                <span class="font-semibold">CHF {{ number_format($adjustment->gross_amount / 100, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endif
        </aside>
    </div>
</x-layouts.admin>
