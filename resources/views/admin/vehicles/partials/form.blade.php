@php($editing = isset($vehicle))
@php($selectedAmenities = old('amenities', $vehicle->amenities ?? []))
@php($rangePricingEnabled = (bool) old('range_pricing_enabled', ($vehicle->pricing_mode ?? 'simple') === 'range'))
@php($rangeBaseDistanceKm = old('range_base_distance_km', isset($vehicle) ? number_format(($vehicle->range_base_distance_m ?? 0) / 1000, 3, '.', '') : '0'))
@php($rangeBaseFare = old('range_base_fare', $vehicle->range_base_fare ?? 0))
@php($rangeRows = old('pricing_ranges', isset($vehicle) && $vehicle->relationLoaded('distancePriceRanges') && $vehicle->distancePriceRanges->isNotEmpty() ? $vehicle->distancePriceRanges->map(fn ($range) => ['end_km' => $range->end_distance_m === null ? '' : number_format((($vehicle->range_base_distance_m ?? 0) + $range->end_distance_m) / 1000, 3, '.', ''), 'price_per_km' => $range->price_per_km, 'minimum_amount' => $range->minimum_amount])->all() : [['end_km' => '', 'price_per_km' => '', 'minimum_amount' => 0]]))
<div class="mb-5 flex flex-wrap gap-2" data-vehicle-wizard>
    <button type="button" data-vehicle-step="details" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-bold text-white">1. Details</button>
    <button type="button" data-vehicle-step="pricing" class="rounded-lg bg-white px-3 py-2 text-sm font-bold text-slate-500 ring-1 ring-slate-200">2. Pricing</button>
    @if($editing)<button type="button" data-vehicle-step="media" class="rounded-lg bg-white px-3 py-2 text-sm font-bold text-slate-500 ring-1 ring-slate-200">3. Images</button><button type="button" data-vehicle-step="publish" class="rounded-lg bg-white px-3 py-2 text-sm font-bold text-slate-500 ring-1 ring-slate-200">4. Publish</button>@endif
</div>

<form method="POST" action="{{ $action }}" class="grid max-w-6xl gap-6 xl:grid-cols-[minmax(0,1fr)_290px]" data-vehicle-form>
    @csrf
    @if($method !== 'POST') @method($method) @endif
    <div class="admin-card overflow-hidden">
        <section data-vehicle-panel="details" class="p-6 sm:p-7">
            <div class="flex items-start gap-4"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-100 font-bold text-indigo-700">1</span><div><h2 class="text-lg font-bold text-slate-900">Vehicle details</h2><p class="mt-1 text-sm leading-6 text-slate-500">Use the customer-facing name and description here. The model field stays internal.</p></div></div>
            <div class="mt-7 grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="admin-label">Display name</label><input class="admin-input" name="name" required maxlength="120" value="{{ old('name', $vehicle->name ?? '') }}" placeholder="Mercedes E-Class"><p class="mt-1 text-xs text-slate-400">This is the name customers see on the website.</p></div>
                <div><label class="admin-label">Internal model reference</label><input class="admin-input" name="model_name" maxlength="120" value="{{ old('model_name', $vehicle->model_name ?? '') }}" placeholder="E 220d"></div>
                <div><div class="flex items-center justify-between"><label class="admin-label">Website category</label><a class="text-xs font-bold text-indigo-700" href="{{ route('admin.vehicle-categories.index') }}">Manage categories</a></div><select class="admin-input" name="category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int) old('category_id', $vehicle->category_id ?? 0) === $category->id)>{{ $category->name }}{{ ! $category->is_active ? ' (hidden)' : '' }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-400">Categories control public vehicle tabs. Add or manage them from the category setup page.</p></div>
                <div><label class="admin-label">Passenger capacity</label><input class="admin-input" name="passenger_capacity" type="number" min="1" max="20" required value="{{ old('passenger_capacity', $vehicle->passenger_capacity ?? 3) }}"></div>
                <div><label class="admin-label">Luggage capacity</label><input class="admin-input" name="luggage_capacity" type="number" min="0" max="30" required value="{{ old('luggage_capacity', $vehicle->luggage_capacity ?? 3) }}"></div>
                <div class="sm:col-span-2"><label class="admin-label">Website slug</label><input class="admin-input" name="slug" maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="{{ old('slug', $vehicle->slug ?? '') }}" placeholder="Generated from the name if left blank"><p class="mt-1 text-xs text-slate-400">Lowercase letters, numbers and dashes only.</p></div>
                <div class="sm:col-span-2"><label class="admin-label">Short description</label><textarea class="admin-input" rows="5" name="description" maxlength="4000" placeholder="A concise, customer-facing description of the vehicle.">{{ old('description', $vehicle->description ?? '') }}</textarea></div>
            </div>
            <div class="mt-7 border-t border-slate-100 pt-6"><h3 class="font-bold text-slate-900">Included amenities</h3><p class="mt-1 text-sm text-slate-500">Select only what this vehicle consistently includes.</p><div class="mt-4 grid gap-3 sm:grid-cols-2">@foreach($amenities as $amenity)<label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold text-slate-700"><input type="checkbox" name="amenities[]" value="{{ $amenity }}" @checked(in_array($amenity, $selectedAmenities, true))>{{ $amenity }}</label>@endforeach</div></div>
        </section>

        <section data-vehicle-panel="pricing" class="hidden p-6 sm:p-7">
            <div class="flex items-start gap-4"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-100 font-bold text-indigo-700">2</span><div><h2 class="text-lg font-bold text-slate-900">Pricing</h2><p class="mt-1 text-sm leading-6 text-slate-500">Choose one simple distance rate, or a base fare with distance ranges for longer journeys.</p></div></div>
            <div class="mt-6 rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm leading-6 text-indigo-900">Prices are entered in <strong>rappen</strong> and are currently <strong>{{ $vatIncluded ? 'VAT inclusive' : 'VAT exclusive' }}</strong>. If this VAT mode changes, existing rates are interpreted in the new mode for future quotes.</div>
            <div class="mt-6 flex items-start justify-between gap-5 rounded-xl border border-slate-200 p-5"><div><h3 class="font-bold text-slate-900">Use range-based pricing</h3><p class="mt-1 max-w-xl text-sm leading-6 text-slate-500">Set a base fare for an included distance, then charge different rates for the excess distance. Existing simple pricing is kept when this is switched off.</p></div><input type="hidden" name="range_pricing_enabled" value="0"><label class="flex shrink-0 cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-700"><input class="size-5 rounded border-slate-300 text-indigo-600" type="checkbox" name="range_pricing_enabled" value="1" data-range-toggle @checked($rangePricingEnabled)><span>Enable</span></label></div>

            <div class="mt-6" data-simple-pricing>
                <h3 class="font-bold text-slate-900">Simple per-kilometre price</h3>
                <div class="mt-4 grid gap-5 sm:grid-cols-2"><div><label class="admin-label">Price per km (rappen)</label><input class="admin-input" name="price_per_km" type="number" min="1" value="{{ old('price_per_km', $vehicle->price_per_km ?? '') }}" placeholder="350"><p class="mt-1 text-xs leading-5 text-slate-400">350 = CHF 3.50/km. Leave empty for “Price on request”.</p></div><div><label class="admin-label">Minimum fare (rappen)</label><input class="admin-input" name="minimum_fare" type="number" min="0" value="{{ old('minimum_fare', $vehicle->minimum_fare ?? '') }}" placeholder="8000"><p class="mt-1 text-xs leading-5 text-slate-400">Optional floor. Leave empty when no minimum applies.</p></div></div>
            </div>

            <div class="mt-6 hidden" data-range-pricing>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900"><strong>How it works:</strong> the base fare applies up to the included distance. Configure ranges using the total trip distance—for example 30–50, 50–80, then 80+. The rate calculation still charges only the kilometres beyond the base distance.</div>
                <div class="mt-5 grid gap-5 sm:grid-cols-2"><div><label class="admin-label">Included base distance (km)</label><input class="admin-input" name="range_base_distance_km" type="number" min="0" step="0.001" value="{{ $rangeBaseDistanceKm }}" placeholder="30"><p class="mt-1 text-xs text-slate-400">For a 70 km trip with a 30 km base, the tiers use 40 excess km.</p></div><div><label class="admin-label">Base fare (rappen)</label><input class="admin-input" name="range_base_fare" type="number" min="0" value="{{ $rangeBaseFare }}" placeholder="8000"><p class="mt-1 text-xs text-slate-400">This is the total fare for every trip at or below the included distance.</p></div></div>
                <div class="mt-7"><div class="flex flex-wrap items-end justify-between gap-3"><div><h3 class="font-bold text-slate-900">Distance ranges</h3><p class="mt-1 text-sm text-slate-500">The first range starts at the included base distance. Later starts are set from the preceding end. Leave the final end blank for “and above”.</p></div><button class="admin-button-soft" type="button" data-add-range>+ Add range</button></div>
                    <div class="mt-4"><div class="space-y-3" data-range-list>
                        @foreach($rangeRows as $index => $rangeRow)
                            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white" data-range-row>
                                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between" data-range-summary>
                                    <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="font-bold text-slate-900" data-range-summary-distance>Range</p><span class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-bold text-indigo-700" data-range-summary-rate>Rate</span><span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600" data-range-summary-minimum>Minimum</span></div><p class="mt-2 hidden text-sm font-semibold text-rose-700" data-range-issue>ⓘ Missing information</p></div>
                                    <div class="flex shrink-0 gap-2"><button class="hidden rounded-lg px-3 py-2 text-sm font-bold text-rose-700 hover:bg-rose-100" type="button" data-delete-summary>Delete</button><button class="admin-button-soft" type="button" data-edit-range>Edit</button></div>
                                </div>
                                <div class="hidden border-t border-slate-100 bg-slate-50 p-4" data-range-editor>
                                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><div><label class="admin-label">Starts at</label><output class="block rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-700" data-range-start>0 km</output></div><div><label class="admin-label">Ends at (km)</label><input class="admin-input" data-range-end name="pricing_ranges[{{ $index }}][end_km]" type="number" min="0" step="0.001" value="{{ $rangeRow['end_km'] ?? '' }}" placeholder="Blank = and above"></div><div><label class="admin-label">Price/km (rappen)</label><input class="admin-input" data-range-rate name="pricing_ranges[{{ $index }}][price_per_km]" type="number" min="1" value="{{ $rangeRow['price_per_km'] ?? '' }}" placeholder="350"></div><div><label class="admin-label" data-minimum-label>Minimum amount</label><input class="admin-input" data-range-minimum name="pricing_ranges[{{ $index }}][minimum_amount]" type="number" min="0" value="{{ $rangeRow['minimum_amount'] ?? 0 }}" placeholder="0"></div></div>
                                    <div class="mt-4 flex items-center justify-between gap-3"><p class="text-xs text-slate-500">Changes are reflected in the summary immediately.</p><div class="flex gap-2"><button class="admin-button-soft" type="button" data-cancel-range>Done</button><button class="hidden rounded-lg px-3 py-2 text-sm font-bold text-rose-700 hover:bg-rose-100" type="button" data-remove-range>Delete final range</button></div></div>
                                </div>
                            </article>
                        @endforeach
                    </div></div>
                </div>
            </div>
        </section>

        @if($editing)<section data-vehicle-panel="publish" class="hidden p-6 sm:p-7"><div class="flex items-start gap-4"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-100 font-bold text-indigo-700">4</span><div><h2 class="text-lg font-bold text-slate-900">Publish vehicle</h2><p class="mt-1 text-sm leading-6 text-slate-500">Control whether customers can see this vehicle in the catalogue.</p></div></div><div class="mt-6 rounded-xl {{ $vehicle->is_active ? 'border border-emerald-200 bg-emerald-50' : 'border border-amber-200 bg-amber-50' }} p-5"><input type="hidden" name="is_active" value="0"><label class="flex items-start gap-4"><input class="mt-1 size-5 rounded border-slate-300 text-indigo-600" type="checkbox" name="is_active" value="1" @checked(old('is_active', $vehicle->is_active))><span><span class="block font-bold text-slate-900">Show on the website</span><span class="mt-1 block text-sm leading-6 text-slate-600">Images are recommended but not required. A vehicle without a per-km rate remains visible as “Price on request”.</span></span></label></div></section>@endif

        <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-slate-500">Changes only affect future quotes. Existing booking prices remain unchanged.</p><button class="admin-button">{{ $editing ? 'Save vehicle changes' : 'Create vehicle draft' }}</button></div>
    </div>

    <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
        <div class="admin-card p-5"><p class="text-xs font-bold uppercase tracking-[.12em] text-slate-400">Configuration</p><div class="mt-4 space-y-3 text-sm"><div class="flex justify-between gap-3"><span class="text-slate-500">Website order</span><input class="w-20 rounded-lg border-slate-200 bg-slate-50 px-2 py-1 text-right font-bold text-slate-800" type="number" name="sort_order" min="0" max="65535" required value="{{ old('sort_order', $vehicle->sort_order ?? 0) }}"></div><div class="flex justify-between gap-3"><span class="text-slate-500">Rate mode</span><span class="font-bold text-slate-800" data-rate-mode>{{ $rangePricingEnabled ? 'Range based' : 'Per kilometre' }}</span></div></div></div>
        @if($editing)<div class="admin-card p-5" data-pricing-tester data-url="{{ route('admin.vehicles.pricing-test', $vehicle) }}"><p class="text-xs font-bold uppercase tracking-[.12em] text-slate-400">Price tester</p><p class="mt-2 text-sm leading-5 text-slate-500">Tests the currently saved vehicle pricing using the same backend calculation as quotes.</p><label class="admin-label mt-4">Trip distance (km)</label><div class="mt-1 flex gap-2"><input class="admin-input !mt-0 min-w-0" type="number" min="0" step="0.001" placeholder="70" data-pricing-test-distance><button class="admin-button shrink-0" type="button" data-pricing-test-button>Calculate</button></div><p class="mt-3 hidden rounded-lg bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-800" data-pricing-test-result></p><p class="mt-3 hidden text-sm font-semibold text-rose-700" data-pricing-test-error></p><p class="mt-3 text-xs leading-5 text-slate-400">Save any pricing edits first, then test the saved configuration.</p></div>@endif
        @if($editing)<div class="admin-card p-5"><p class="text-xs font-bold uppercase tracking-[.12em] text-slate-400">Readiness</p><div class="mt-4 space-y-3 text-sm"><div class="flex justify-between"><span class="text-slate-500">Images</span><span class="font-bold text-slate-800">{{ $vehicle->media->where('collection_name', 'images')->count() }}</span></div><div class="flex justify-between"><span class="text-slate-500">Online price</span><span class="font-bold {{ $vehicle->pricing_mode === 'range' || $vehicle->price_per_km !== null ? 'text-emerald-700' : 'text-amber-700' }}">{{ $vehicle->pricing_mode === 'range' || $vehicle->price_per_km !== null ? 'Set' : 'Missing' }}</span></div></div></div>@endif
    </aside>
</form>

<script>
    (() => {
        const tabs = [...document.querySelectorAll('[data-vehicle-step]')];
        const panels = [...document.querySelectorAll('[data-vehicle-panel]')];
        const select = step => { panels.forEach(panel => panel.classList.toggle('hidden', panel.dataset.vehiclePanel !== step)); tabs.forEach(tab => { const active = tab.dataset.vehicleStep === step; tab.classList.toggle('bg-indigo-600', active); tab.classList.toggle('text-white', active); tab.classList.toggle('bg-white', !active); tab.classList.toggle('text-slate-500', !active); }); document.querySelector(`[data-media-panel]`)?.classList.toggle('hidden', step !== 'media'); };
        tabs.forEach(tab => tab.addEventListener('click', () => select(tab.dataset.vehicleStep)));

        const toggle = document.querySelector('[data-range-toggle]');
        const simple = document.querySelector('[data-simple-pricing]');
        const ranges = document.querySelector('[data-range-pricing]');
        const rateMode = document.querySelector('[data-rate-mode]');
        const list = document.querySelector('[data-range-list]');
        const formatKm = metres => `${(metres / 1000).toFixed(3).replace(/\.0+$/, '')} km`;
        const formatChf = rappen => `CHF ${(Number(rappen || 0) / 100).toFixed(2)}`;
        const rangeRows = () => [...(list?.querySelectorAll('[data-range-row]') ?? [])];
        const setEditing = activeRow => rangeRows().forEach(row => {
            const active = row === activeRow;
            row.querySelector('[data-range-summary]').classList.toggle('hidden', active);
            row.querySelector('[data-range-editor]').classList.toggle('hidden', !active);
            row.classList.toggle('border-indigo-300', active);
            row.classList.toggle('ring-2', active);
            row.classList.toggle('ring-indigo-100', active);
        });
        const updateRows = () => {
            const baseKm = Number(document.querySelector('[name="range_base_distance_km"]')?.value) || 0;
            let startKm = baseKm;
            const rows = rangeRows();
            rows.forEach((row, index) => {
                const end = row.querySelector('[data-range-end]');
                const rate = row.querySelector('[data-range-rate]');
                const minimum = row.querySelector('[data-range-minimum]');
                const minimumLabel = row.querySelector('[data-minimum-label]');
                const isLast = index === rows.length - 1;
                const startForRow = startKm;
                [end, rate, minimum].forEach(input => input.name = input.name.replace(/pricing_ranges\[\d+\]/, `pricing_ranges[${index}]`));
                row.querySelector('[data-range-start]').textContent = formatKm(Math.round(startForRow * 1000));
                const first = index === 0;
                minimum.readOnly = !first;
                minimum.classList.toggle('bg-slate-100', !first);
                minimumLabel.textContent = first ? 'Minimum amount' : 'Minimum (auto)';
                let previousComplete = true;
                if (!first) {
                    const previous = rows[index - 1];
                    const previousEnd = Number(previous.querySelector('[data-range-end]').value);
                    const previousRate = Number(previous.querySelector('[data-range-rate]').value);
                    previousComplete = previous.querySelector('[data-range-end]').value !== '' && previousRate > 0;
                    minimum.value = previousComplete ? Math.ceil(Math.max(0, previousEnd - baseKm) * previousRate) : 0;
                }
                const endValue = Number(end.value);
                const hasEnd = end.value !== '' && Number.isFinite(endValue);
                const issues = [];
                if (!(Number(rate.value) > 0)) issues.push('price per km is missing');
                if (!isLast && !hasEnd) issues.push('end distance is missing');
                if (hasEnd && endValue <= startForRow) issues.push('end must be after the start');
                if (!first && !previousComplete) issues.push('previous range needs an end and rate');
                const issue = row.querySelector('[data-range-issue]');
                issue.textContent = issues.length ? `ⓘ ${issues.join(' · ')}` : '';
                issue.classList.toggle('hidden', issues.length === 0);
                row.classList.toggle('border-rose-300', issues.length > 0);
                row.classList.toggle('bg-rose-50', issues.length > 0);
                row.querySelector('[data-range-summary-distance]').textContent = `${formatKm(Math.round(startForRow * 1000))} – ${hasEnd ? formatKm(Math.round(endValue * 1000)) : 'and above'}`;
                row.querySelector('[data-range-summary-rate]').textContent = Number(rate.value) > 0 ? `${formatChf(rate.value)}/km` : 'Rate missing';
                row.querySelector('[data-range-summary-minimum]').textContent = `Min. ${formatChf(minimum.value)}`;
                row.querySelector('[data-remove-range]').classList.toggle('hidden', !isLast || rows.length === 1);
                row.querySelector('[data-delete-summary]').classList.toggle('hidden', !isLast || rows.length === 1);
                if (hasEnd) startKm = endValue;
            });
        };
        const updateMode = () => {
            if (!toggle) return;
            const enabled = toggle.checked;
            simple?.classList.toggle('hidden', enabled);
            ranges?.classList.toggle('hidden', !enabled);
            rateMode.textContent = enabled ? 'Range based' : 'Per kilometre';
            simple?.querySelectorAll('input').forEach(input => input.disabled = enabled);
            ranges?.querySelectorAll('input').forEach(input => input.disabled = !enabled);
            updateRows();
        };
        const addRow = () => {
            if (!list) return;
            const index = list.querySelectorAll('[data-range-row]').length;
            const row = document.createElement('article');
            row.className = 'overflow-hidden rounded-xl border border-slate-200 bg-white';
            row.dataset.rangeRow = '';
            row.innerHTML = `<div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between" data-range-summary><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="font-bold text-slate-900" data-range-summary-distance>New range</p><span class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-bold text-indigo-700" data-range-summary-rate>Rate missing</span><span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600" data-range-summary-minimum>Min. CHF 0.00</span></div><p class="mt-2 hidden text-sm font-semibold text-rose-700" data-range-issue>ⓘ Missing information</p></div><div class="flex shrink-0 gap-2"><button class="hidden rounded-lg px-3 py-2 text-sm font-bold text-rose-700 hover:bg-rose-100" type="button" data-delete-summary>Delete</button><button class="admin-button-soft" type="button" data-edit-range>Edit</button></div></div><div class="hidden border-t border-slate-100 bg-slate-50 p-4" data-range-editor><div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><div><label class="admin-label">Starts at</label><output class="block rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-700" data-range-start>0 km</output></div><div><label class="admin-label">Ends at (km)</label><input class="admin-input" data-range-end name="pricing_ranges[${index}][end_km]" type="number" min="0" step="0.001" placeholder="Blank = and above"></div><div><label class="admin-label">Price/km (rappen)</label><input class="admin-input" data-range-rate name="pricing_ranges[${index}][price_per_km]" type="number" min="1" placeholder="350"></div><div><label class="admin-label" data-minimum-label>Minimum (auto)</label><input class="admin-input bg-slate-100" data-range-minimum name="pricing_ranges[${index}][minimum_amount]" type="number" min="0" value="0" readonly></div></div><div class="mt-4 flex items-center justify-between gap-3"><p class="text-xs text-slate-500">Changes are reflected in the summary immediately.</p><div class="flex gap-2"><button class="admin-button-soft" type="button" data-cancel-range>Done</button><button class="hidden rounded-lg px-3 py-2 text-sm font-bold text-rose-700 hover:bg-rose-100" type="button" data-remove-range>Delete final range</button></div></div></div>`;
            list.append(row);
            updateRows();
            setEditing(row);
            row.querySelector('[data-range-rate]').focus();
        };
        toggle?.addEventListener('change', updateMode);
        document.querySelector('[data-add-range]')?.addEventListener('click', addRow);
        list?.addEventListener('input', updateRows);
        document.querySelector('[name="range_base_distance_km"]')?.addEventListener('input', updateRows);
        list?.addEventListener('click', event => {
            const row = event.target.closest('[data-range-row]');
            if (!row) return;
            if (event.target.closest('[data-edit-range]')) setEditing(row);
            if (event.target.closest('[data-cancel-range]')) setEditing(null);
            if (event.target.closest('[data-remove-range], [data-delete-summary]')) {
                const rows = rangeRows();
                if (row !== rows.at(-1) || rows.length === 1) return;
                rows.at(-2).querySelector('[data-range-end]').value = '';
                row.remove();
                updateRows();
                setEditing(null);
            }
        });
        const tester = document.querySelector('[data-pricing-tester]');
        const testButton = tester?.querySelector('[data-pricing-test-button]');
        testButton?.addEventListener('click', async () => {
            const distance = tester.querySelector('[data-pricing-test-distance]');
            const result = tester.querySelector('[data-pricing-test-result]');
            const error = tester.querySelector('[data-pricing-test-error]');
            const distanceKm = Number(distance.value);
            result.classList.add('hidden');
            error.classList.add('hidden');
            if (distance.value === '' || !Number.isFinite(distanceKm) || distanceKm < 0) {
                error.textContent = 'Enter a valid trip distance.';
                error.classList.remove('hidden');
                return;
            }
            testButton.disabled = true;
            testButton.textContent = 'Calculating…';
            try {
                const response = await fetch(tester.dataset.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({distance_km: distanceKm}),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'Could not calculate the test price.');
                result.textContent = `${payload.data.distance_km} km → ${payload.data.formatted_amount}`;
                result.classList.remove('hidden');
            } catch (problem) {
                error.textContent = problem.message;
                error.classList.remove('hidden');
            } finally {
                testButton.disabled = false;
                testButton.textContent = 'Calculate';
            }
        });
        updateMode();
    })();
</script>
