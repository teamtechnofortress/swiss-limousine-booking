<dialog id="refund-modal-{{ $payment->id }}" data-refund-modal data-currency="{{ $payment->currency }}" data-available="{{ $availableToRefund }}" data-stripe-fee="{{ $stripeFee }}" class="fixed left-1/2 top-1/2 m-0 max-h-[calc(100vh-2rem)] w-[calc(100vw-2rem)] max-w-2xl -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/55">
    <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" data-refund-form class="max-h-[calc(100vh-2rem)] overflow-y-auto">
        @csrf
        <input type="hidden" name="gross_amount" data-refund-amount>

        <header class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.14em] text-rose-600">Refund payment</p>
                <h2 class="mt-1 text-lg font-bold text-slate-950">{{ $payment->booking->reference }}</h2>
                <p class="mt-0.5 text-sm text-slate-500">{{ $payment->booking->customer_name }} · {{ $payment->currency }} {{ number_format($availableToRefund / 100, 2) }} available</p>
            </div>
            <button type="button" data-refund-close class="grid size-9 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900" aria-label="Close refund dialog">×</button>
        </header>

        <div class="space-y-5 p-5">
            <section class="rounded-xl border border-slate-200">
                <dl class="grid divide-y divide-slate-100 text-sm sm:grid-cols-4 sm:divide-x sm:divide-y-0">
                    <div class="space-y-1 p-3"><dt class="text-xs text-slate-500">Customer paid</dt><dd class="font-bold text-slate-900">{{ $payment->currency }} {{ number_format($payment->gross_amount / 100, 2) }}</dd></div>
                    <div class="space-y-1 p-3"><dt class="text-xs text-slate-500">Available</dt><dd class="font-bold text-emerald-700">{{ $payment->currency }} {{ number_format($availableToRefund / 100, 2) }}</dd></div>
                    <div class="space-y-1 p-3"><dt class="text-xs text-slate-500">Stripe fee</dt><dd class="font-bold text-slate-900">{{ $payment->provider_ledger_synced_at ? $payment->provider_balance_currency.' '.number_format($payment->provider_fee_amount / 100, 2) : 'Pending' }}</dd></div>
                    <div class="space-y-1 p-3"><dt class="text-xs text-slate-500">Settlement net</dt><dd class="font-bold text-slate-900">{{ $payment->provider_ledger_synced_at ? $payment->provider_balance_currency.' '.number_format($payment->provider_net_amount / 100, 2) : 'Pending' }}</dd></div>
                </dl>
                @if($reserved > 0)<p class="border-t border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ $payment->currency }} {{ number_format($reserved / 100, 2) }} is awaiting Stripe confirmation.</p>@endif
            </section>

            <section>
                <p class="text-sm font-bold text-slate-900">Customer refund</p>

                <div class="mt-3 grid gap-2 sm:grid-cols-2" data-refund-options>
                    <label class="cursor-pointer rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition hover:border-slate-300 has-[:checked]:border-rose-600 has-[:checked]:bg-rose-50 has-[:checked]:ring-1 has-[:checked]:ring-rose-500">
                        <input class="sr-only" type="radio" name="refund_choice" value="full" data-refund-choice>
                        <span class="flex items-center justify-between gap-3"><span class="font-bold text-slate-900">Full amount</span><span class="text-sm font-bold text-slate-700" data-refund-option-total="full">—</span></span>
                    </label>
                    @foreach([75, 50, 25] as $percentage)
                        <label class="cursor-pointer rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition hover:border-slate-300 has-[:checked]:border-indigo-600 has-[:checked]:ring-1 has-[:checked]:ring-indigo-500">
                            <input class="sr-only" type="radio" name="refund_choice" value="percentage" data-refund-choice data-refund-percentage="{{ $percentage }}">
                            <span class="flex items-center justify-between gap-3"><span class="font-bold text-slate-900">{{ $percentage }}% amount</span><span class="text-sm font-bold text-slate-700" data-refund-option-total="percentage-{{ $percentage }}">—</span></span>
                        </label>
                    @endforeach
                    <label class="cursor-pointer rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition hover:border-slate-300 has-[:checked]:border-indigo-600 has-[:checked]:ring-1 has-[:checked]:ring-indigo-500">
                        <input class="sr-only" type="radio" name="refund_choice" value="custom" data-refund-choice>
                        <span class="flex items-center justify-between gap-3"><span class="font-bold text-slate-900">Custom amount</span><span class="text-sm text-slate-500">Enter amount</span></span>
                    </label>
                </div>

                <div data-refund-custom-wrap class="mt-3 hidden max-w-sm">
                    <label class="admin-label" for="refund-custom-{{ $payment->id }}">Custom refund ({{ $payment->currency }})</label>
                    <input id="refund-custom-{{ $payment->id }}" data-refund-custom type="number" min="0" step="0.01" inputmode="decimal" class="admin-input" placeholder="0.00">
                </div>

                <label class="mt-3 flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 {{ $stripeFee > 0 ? '' : 'opacity-60' }}">
                    <span><span class="block text-sm font-semibold text-slate-900">Include Stripe processing fee</span><span class="block text-xs text-slate-500">{{ $stripeFee > 0 ? '+'.$payment->currency.' '.number_format($stripeFee / 100, 2).' to customer refund' : 'Fee data is not available' }}</span></span>
                    <input type="checkbox" data-refund-fee class="size-4 rounded border-slate-300 text-rose-700 focus:ring-rose-600" @disabled($stripeFee === 0)>
                </label>

                @if ($payment->method === \App\Enums\PaymentMethod::Online && $payment->booking->status->canTransitionTo(\App\Enums\BookingStatus::Cancelled))
                    <label class="mt-2 flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <span><span class="block text-sm font-semibold text-slate-900">Cancel booking after refund succeeds</span><span class="block text-xs text-slate-500">The booking remains active until Stripe’s success webhook is received.</span></span>
                        <input type="checkbox" name="cancel_booking_on_success" value="1" class="size-4 rounded border-slate-300 text-rose-700 focus:ring-rose-600">
                    </label>
                @endif

                <div class="mt-3 flex items-center justify-between gap-4 rounded-xl bg-slate-950 px-4 py-3 text-white">
                    <span class="text-sm text-slate-300">Customer receives</span>
                    <span class="text-lg font-bold text-emerald-300" data-refund-total>Choose an option</span>
                </div>
            </section>

            <div>
                <label class="admin-label" for="refund-reason-{{ $payment->id }}">Reason for refund</label>
                <textarea id="refund-reason-{{ $payment->id }}" class="admin-input" required maxlength="500" name="reason" rows="2" placeholder="Reason for refund"></textarea>
            </div>
        </div>

        <footer class="sticky bottom-0 flex flex-col-reverse gap-3 border-t border-slate-200 bg-white px-5 py-3 sm:flex-row sm:justify-end">
            <button type="button" data-refund-close class="admin-button-soft">Cancel</button>
            <button class="admin-button !bg-rose-700 hover:!bg-rose-800 disabled:cursor-not-allowed disabled:opacity-50" data-refund-submit disabled>Select a refund option</button>
        </footer>
    </form>
</dialog>
