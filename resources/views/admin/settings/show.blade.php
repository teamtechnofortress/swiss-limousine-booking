<x-layouts.admin :title="$title">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div><a href="{{ route('admin.settings.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">← All settings</a><p class="mt-2 text-sm text-slate-500">{{ $canEdit ? 'Changes take effect for new requests after saving.' : 'You can review these operating values, but your role cannot change them.' }}</p></div>
        @if($group === 'integrations' && auth()->user()->can('integrations.manage'))<a href="{{ route('admin.mail.show') }}" class="admin-button-soft">Mail delivery →</a>@endif
    </div>

    <form method="POST" action="{{ route('admin.settings.update', $group) }}" class="admin-card divide-y divide-slate-100 overflow-hidden">
        @csrf
        @method('PUT')
        @foreach($settings as $setting)
            @if($group === 'service_area' && $setting->key === 'allowed_country_names')
                @continue
            @endif
            @if($group === 'service_area' && $setting->key === 'allowed_countries')
                <section class="mx-5 mb-5 overflow-hidden rounded-2xl border border-blue-100 bg-blue-50/30 shadow-sm" data-service-area-scope>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-blue-100 bg-blue-50 px-5 py-3">
                        <div><p class="text-sm font-bold text-slate-900">Service area rules</p><p class="text-xs text-slate-500">Controlled by Service Area Enforced</p></div>
                        <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-blue-700">Level 1</span>
                    </div>
                    <div class="m-5 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600" data-service-area-disabled-notice>
                        Service-area rules are disabled. The saved country and location settings are retained, but they are not applied to new quotes.
                    </div>
                    <div class="divide-y divide-slate-100">
            @endif
            @if($group === 'service_area' && $setting->key === 'required_location_id')
                <section class="m-5 overflow-hidden rounded-xl border border-violet-200 bg-violet-50/30 shadow-sm" data-required-location-scope>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-violet-100 bg-violet-50 px-4 py-3">
                        <div><p class="text-sm font-bold text-slate-900">Required location rule</p><p class="text-xs text-slate-500">Controlled by Required Location Enabled</p></div>
                        <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-violet-700">Level 2</span>
                    </div>
                    <div class="mx-5 mt-5 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600" data-required-location-disabled-notice>
                        Required-location matching is disabled. New quotes may start and end anywhere within the allowed countries.
                    </div>
                    <div class="divide-y divide-slate-100">
            @endif
            @if($group === 'tax' && $setting->key === 'vat_included')
                <section class="mx-5 mb-5 overflow-hidden rounded-2xl border border-blue-100 bg-blue-50/30 shadow-sm" data-vat-scope>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-blue-100 bg-blue-50 px-5 py-3">
                        <div><p class="text-sm font-bold text-slate-900">VAT configuration</p><p class="text-xs text-slate-500">Controlled by VAT Enabled</p></div>
                        <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-blue-700">Level 1</span>
                    </div>
                    <div class="m-5 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600" data-vat-disabled-notice>
                        VAT is disabled. These saved settings are retained, but VAT is not applied to new quotes or bookings.
                    </div>
                    <div class="divide-y divide-slate-100">
            @endif
            @php
                $current = old('settings.'.$setting->key, $setting->is_secret ? '' : ($values[$setting->key] ?? ''));
                $selectOptions = match ($setting->key) {
                    'stripe_mode' => ['test' => 'Test mode', 'live' => 'Live mode'],
                    'payment_gateway' => ['stripe' => 'Stripe'],
                    'required_location_match_type' => ['city' => 'City', 'region' => 'Region', 'radius' => 'Radius'],
                    default => [],
                };
            @endphp
            <div class="grid gap-4 p-5 md:grid-cols-[minmax(0,1fr)_minmax(260px,420px)] md:items-center @if($group === 'service_area' && $setting->key === 'required_location_radius_km') mx-5 mb-5 rounded-xl border border-amber-200 bg-amber-50/50 @endif" @if($group === 'service_area' && $setting->key === 'required_location_radius_km') data-required-location-radius-scope @endif>
                <div>
                    <label for="setting_{{ $setting->key }}" class="font-semibold text-slate-900">{{ $group === 'service_area' && $setting->key === 'required_location_id' ? 'Required location' : str($setting->key)->replace('_', ' ')->title() }}</label>
                    <p class="mt-1 max-w-xl text-sm leading-5 text-slate-500">{{ $setting->description }}</p>
                    @if($group === 'service_area' && $setting->key === 'required_location_radius_km')<p class="mt-2 text-xs font-bold uppercase tracking-wide text-amber-700">Level 3 · Only applies when Match Type is Radius</p>@endif
                    @if($group === 'service_area' && $setting->key === 'service_area_enforced')
                        <p class="mt-2 max-w-xl text-sm font-medium leading-5 text-slate-700" data-service-area-master-summary></p>
                    @endif
                    @if($group === 'tax' && $setting->key === 'vat_enabled')
                        <p class="mt-2 max-w-xl text-sm font-medium leading-5 text-slate-700" data-vat-master-summary></p>
                    @endif
                    @if($setting->is_secret)<p class="mt-2 text-xs font-semibold text-amber-700">Stored encrypted. Leave blank to keep the current value.</p>@endif
                </div>
                <div>
                    @if($group === 'service_area' && $setting->key === 'allowed_countries')
                        @php
                            $countryNames = is_array($values['allowed_country_names'] ?? null) ? $values['allowed_country_names'] : [];
                            $countries = collect((array) ($values['allowed_countries'] ?? []))
                                ->map(fn ($code) => ['code' => $code, 'name' => $countryNames[$code] ?? $code])
                                ->values();
                        @endphp
                        <div
                            data-country-picker
                            data-search-url="{{ route('admin.settings.service-area.countries') }}"
                            data-country-url-template="{{ route('admin.settings.service-area.country', ['placeId' => '__PLACE_ID__']) }}"
                            data-countries='@json($countries)'
                        >
                            <input type="search" class="admin-input" data-country-search placeholder="Search for a country" autocomplete="off" @disabled(! $canEdit)>
                            <p class="mt-2 hidden text-xs font-medium text-rose-600" data-country-error></p>
                            <div class="relative">
                                <div class="absolute z-10 mt-1 hidden w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg" data-country-results></div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2" data-country-selected></div>
                            <input type="hidden" name="settings[allowed_countries]" data-country-codes>
                            <input type="hidden" name="settings[allowed_country_names]" data-country-names>
                        </div>
                    @elseif($setting->key === 'site_logo_url')
                        <div data-site-logo-upload data-upload-url="{{ route('admin.media.upload') }}">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50" data-site-logo-preview>
                                    @if($current)<img src="{{ $current }}" alt="Current site logo" class="size-full object-contain">@else<span class="text-xs font-semibold text-slate-400">Logo</span>@endif
                                </div>
                                <div class="min-w-0 flex-1"><input data-site-logo-file class="block w-full text-sm text-slate-600" type="file" accept="image/jpeg,image/png,image/webp" @disabled(! $canEdit)><p class="mt-1 text-xs text-slate-500">PNG, JPG or WebP · max 4 MB. Uploading replaces the current logo.</p></div>
                                @if($canEdit)<button data-site-logo-upload-button type="button" class="admin-button-soft">Upload logo</button>@endif
                            </div>
                            <p data-site-logo-upload-error class="mt-2 hidden text-xs font-medium text-rose-600"></p>
                            <input data-site-logo-url id="setting_{{ $setting->key }}" class="admin-input mt-3" name="settings[{{ $setting->key }}]" value="{{ $current }}" placeholder="Or paste a public logo URL" @disabled(! $canEdit)>
                        </div>
                    @elseif($group === 'service_area' && $setting->key === 'required_location_id')
                        <x-admin.saved-location-picker
                            id="setting_required_location_id"
                            input-name="settings[required_location_id]"
                            :selected="$requiredLocation ? [$requiredLocation] : []"
                            placeholder="Search active saved locations"
                            help="Only active locations can be selected. The location is used for city, region, or radius matching."
                            :disabled="! $canEdit"
                        />
                    @elseif($setting->type === 'bool')
                        <input type="hidden" name="settings[{{ $setting->key }}]" value="0">
                        <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 @if(! $canEdit) opacity-60 @endif">
                            <span class="text-sm font-semibold text-slate-700" @if($group === 'service_area' && $setting->key === 'service_area_enforced') data-service-area-master-status @endif @if($group === 'tax' && $setting->key === 'vat_enabled') data-vat-master-status @endif>{{ (bool) $current ? 'Enabled' : 'Disabled' }}</span>
                            <input class="size-5 rounded border-slate-300 text-slate-950 focus:ring-slate-900" type="checkbox" name="settings[{{ $setting->key }}]" value="1" @checked((bool) $current) @disabled(! $canEdit) @if($group === 'service_area' && $setting->key === 'service_area_enforced') data-service-area-master @endif @if($group === 'service_area' && $setting->key === 'required_location_enabled') data-required-location-toggle @endif @if($group === 'tax' && $setting->key === 'vat_enabled') data-vat-master @endif>
                        </label>
                        @if($setting->key === 'vat_included')<p class="mt-2 text-xs font-semibold leading-5 text-amber-700">Warning: changing this does not recalculate existing vehicle rates. Their entered amounts will be interpreted in the newly selected VAT mode for all future quotes.</p>@endif
                    @elseif($setting->type === 'json')
                        <textarea id="setting_{{ $setting->key }}" class="admin-input font-mono text-xs" name="settings[{{ $setting->key }}]" rows="4" @disabled(! $canEdit)>{{ is_array($current) ? json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $current }}</textarea>
                    @elseif($selectOptions !== [])
                        <select id="setting_{{ $setting->key }}" class="admin-input" name="settings[{{ $setting->key }}]" @disabled(! $canEdit) @if($group === 'service_area' && $setting->key === 'required_location_match_type') data-required-location-match-type @endif>@foreach($selectOptions as $value => $label)<option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>@endforeach</select>
                    @else
                        <input id="setting_{{ $setting->key }}" class="admin-input" name="settings[{{ $setting->key }}]" type="{{ $setting->is_secret ? 'password' : (str_contains($setting->key, 'email') ? 'email' : (in_array($setting->type, ['int', 'money', 'nullable_int', 'nullable_money'], true) ? 'number' : 'text')) }}" value="{{ $setting->is_secret ? '' : $current }}" @if(in_array($setting->type, ['int', 'money', 'nullable_int', 'nullable_money'], true)) step="1" @endif @disabled(! $canEdit) @if($group === 'service_area' && $setting->key === 'required_location_radius_km') data-required-location-radius @endif>
                    @endif
                    @error('settings.'.$setting->key)<p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>
            @if($group === 'service_area' && $setting->key === 'required_location_radius_km')
                    </div>
                </section>
                    </div>
                </section>
            @endif
            @if($group === 'tax' && $setting->key === 'vat_registration_number')
                    </div>
                </section>
            @endif
        @endforeach
        @if($canEdit)<div class="flex justify-end bg-slate-50 px-5 py-4"><button class="admin-button">Save {{ strtolower($title) }}</button></div>@endif
    </form>
    @if($group === 'payment_methods')
        <section class="admin-card mt-6 overflow-hidden">
            <div class="border-b border-violet-100 bg-violet-50 px-5 py-4">
                <p class="font-bold text-slate-900">Stripe setup</p>
                <p class="mt-1 text-sm text-slate-600">Configure Stripe credentials and the webhook Stripe uses to confirm payments.</p>
            </div>
            <form method="POST" action="{{ route('admin.settings.payment-methods.stripe') }}" class="divide-y divide-slate-100">
                @csrf
                @method('PUT')
                @foreach($stripeSettings as $setting)
                    @php
                        $current = old('settings.'.$setting->key, $setting->is_secret ? '' : ($values[$setting->key] ?? ''));
                        $selectOptions = $setting->key === 'stripe_mode' ? ['test' => 'Test mode', 'live' => 'Live mode'] : [];
                    @endphp
                    <div class="grid gap-4 p-5 md:grid-cols-[minmax(0,1fr)_minmax(260px,420px)] md:items-center">
                        <div>
                            <label for="stripe_{{ $setting->key }}" class="font-semibold text-slate-900">{{ str($setting->key)->replace('_', ' ')->title() }}</label>
                            <p class="mt-1 max-w-xl text-sm leading-5 text-slate-500">{{ $setting->description }}</p>
                            @if($setting->is_secret)<p class="mt-2 text-xs font-semibold text-amber-700">Stored encrypted. Leave blank to keep the current value.</p>@endif
                        </div>
                        <div>
                            @if($selectOptions !== [])
                                <select id="stripe_{{ $setting->key }}" class="admin-input" name="settings[{{ $setting->key }}]" @disabled(! $canManageStripe)>@foreach($selectOptions as $value => $label)<option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>@endforeach</select>
                            @else
                                <input id="stripe_{{ $setting->key }}" class="admin-input" name="settings[{{ $setting->key }}]" type="{{ $setting->is_secret ? 'password' : 'text' }}" value="{{ $setting->is_secret ? '' : $current }}" @disabled(! $canManageStripe)>
                            @endif
                            @error('settings.'.$setting->key)<p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endforeach
                <div class="m-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="font-semibold text-slate-900">Stripe webhook destination</p>
                    <p class="mt-1 text-sm leading-5 text-slate-500">Create a Stripe webhook endpoint with this URL, then paste its signing secret above. Select the Checkout, PaymentIntent, and refund events listed below.</p>
                    <div class="mt-3 grid gap-2 text-xs text-slate-600 md:grid-cols-2">
                        <p><span class="font-semibold text-slate-800">Checkout:</span> <code>checkout.session.completed</code>, <code>checkout.session.async_payment_succeeded</code>, <code>checkout.session.async_payment_failed</code>, <code>checkout.session.expired</code></p>
                        <p><span class="font-semibold text-slate-800">PaymentIntent:</span> <code>payment_intent.succeeded</code>, <code>payment_intent.payment_failed</code>, <code>payment_intent.canceled</code></p>
                        <p><span class="font-semibold text-slate-800">Refunds:</span> <code>refund.created</code>, <code>refund.updated</code>, <code>charge.refunded</code></p>
                    </div>
                    <input class="admin-input mt-3 font-mono text-xs" type="text" value="{{ $stripeWebhookUrl }}" readonly aria-label="Stripe webhook destination">
                </div>
                @if($canManageStripe)<div class="flex justify-end bg-slate-50 px-5 py-4"><button class="admin-button">Save Stripe settings</button></div>@endif
            </form>
        </section>
    @endif
    <script>
        (() => {
            const root=document.querySelector('[data-site-logo-upload]');if(!root)return;
            const file=root.querySelector('[data-site-logo-file]'),button=root.querySelector('[data-site-logo-upload-button]'),url=root.querySelector('[data-site-logo-url]'),preview=root.querySelector('[data-site-logo-preview]'),error=root.querySelector('[data-site-logo-upload-error]');
            button?.addEventListener('click',async()=>{if(!file.files?.[0]){error.textContent='Choose a logo image first.';error.classList.remove('hidden');return;}button.disabled=true;button.textContent='Uploading…';error.classList.add('hidden');try{const body=new FormData();body.append('purpose','site_logo');body.append('file',file.files[0]);const response=await fetch(root.dataset.uploadUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body}),json=await response.json();if(!response.ok)throw new Error(json.message||json.errors?.file?.[0]||'Logo upload failed.');url.value=json.data.url;preview.replaceChildren();const image=document.createElement('img');image.src=json.data.url;image.alt='Current site logo';image.className='size-full object-contain';preview.append(image);button.textContent='Uploaded';setTimeout(()=>button.textContent='Replace logo',1200);}catch(problem){error.textContent=problem.message;error.classList.remove('hidden');button.textContent='Upload logo';}finally{button.disabled=false;}});
        })();
    </script>
</x-layouts.admin>
