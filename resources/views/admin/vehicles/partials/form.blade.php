@php($editing = isset($vehicle))
@php($selectedAmenities = old('amenities', $vehicle->amenities ?? []))
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
            <div class="flex items-start gap-4"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-100 font-bold text-indigo-700">2</span><div><h2 class="text-lg font-bold text-slate-900">Pricing</h2><p class="mt-1 text-sm leading-6 text-slate-500">There is no base fare. The fare is distance × your per-kilometre rate, optionally protected by a minimum fare.</p></div></div>
            <div class="mt-6 rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm leading-6 text-indigo-900">Prices are entered in <strong>rappen</strong> and are currently <strong>{{ $vatIncluded ? 'VAT inclusive' : 'VAT exclusive' }}</strong>. If this VAT mode changes, existing rates are interpreted in the new mode for future quotes.</div>
            <div class="mt-6 grid gap-5 sm:grid-cols-2"><div><label class="admin-label">Price per km (rappen)</label><input class="admin-input" name="price_per_km" type="number" min="1" value="{{ old('price_per_km', $vehicle->price_per_km ?? '') }}" placeholder="350"><p class="mt-1 text-xs leading-5 text-slate-400">350 = CHF 3.50/km. Leave empty for “Price on request”; customers cannot book it online.</p></div><div><label class="admin-label">Minimum fare (rappen)</label><input class="admin-input" name="minimum_fare" type="number" min="0" value="{{ old('minimum_fare', $vehicle->minimum_fare ?? '') }}" placeholder="8000"><p class="mt-1 text-xs leading-5 text-slate-400">Optional floor. Leave empty when no minimum applies.</p></div></div>
        </section>

        @if($editing)<section data-vehicle-panel="publish" class="hidden p-6 sm:p-7"><div class="flex items-start gap-4"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-100 font-bold text-indigo-700">4</span><div><h2 class="text-lg font-bold text-slate-900">Publish vehicle</h2><p class="mt-1 text-sm leading-6 text-slate-500">Control whether customers can see this vehicle in the catalogue.</p></div></div><div class="mt-6 rounded-xl {{ $vehicle->is_active ? 'border border-emerald-200 bg-emerald-50' : 'border border-amber-200 bg-amber-50' }} p-5"><input type="hidden" name="is_active" value="0"><label class="flex items-start gap-4"><input class="mt-1 size-5 rounded border-slate-300 text-indigo-600" type="checkbox" name="is_active" value="1" @checked(old('is_active', $vehicle->is_active))><span><span class="block font-bold text-slate-900">Show on the website</span><span class="mt-1 block text-sm leading-6 text-slate-600">Images are recommended but not required. A vehicle without a per-km rate remains visible as “Price on request”.</span></span></label></div></section>@endif

        <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-slate-500">Changes only affect future quotes. Existing booking prices remain unchanged.</p><button class="admin-button">{{ $editing ? 'Save vehicle changes' : 'Create vehicle draft' }}</button></div>
    </div>

    <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
        <div class="admin-card p-5"><p class="text-xs font-bold uppercase tracking-[.12em] text-slate-400">Configuration</p><div class="mt-4 space-y-3 text-sm"><div class="flex justify-between gap-3"><span class="text-slate-500">Website order</span><input class="w-20 rounded-lg border-slate-200 bg-slate-50 px-2 py-1 text-right font-bold text-slate-800" type="number" name="sort_order" min="0" max="65535" required value="{{ old('sort_order', $vehicle->sort_order ?? 0) }}"></div><div class="flex justify-between gap-3"><span class="text-slate-500">Rate mode</span><span class="font-bold text-slate-800">Per kilometre</span></div></div></div>
        @if($editing)<div class="admin-card p-5"><p class="text-xs font-bold uppercase tracking-[.12em] text-slate-400">Readiness</p><div class="mt-4 space-y-3 text-sm"><div class="flex justify-between"><span class="text-slate-500">Images</span><span class="font-bold text-slate-800">{{ $vehicle->media->where('collection_name', 'images')->count() }}</span></div><div class="flex justify-between"><span class="text-slate-500">Online price</span><span class="font-bold {{ $vehicle->price_per_km === null ? 'text-amber-700' : 'text-emerald-700' }}">{{ $vehicle->price_per_km === null ? 'Missing' : 'Set' }}</span></div></div></div>@endif
    </aside>
</form>

<script>
    (() => {
        const tabs = [...document.querySelectorAll('[data-vehicle-step]')];
        const panels = [...document.querySelectorAll('[data-vehicle-panel]')];
        const select = step => { panels.forEach(panel => panel.classList.toggle('hidden', panel.dataset.vehiclePanel !== step)); tabs.forEach(tab => { const active = tab.dataset.vehicleStep === step; tab.classList.toggle('bg-indigo-600', active); tab.classList.toggle('text-white', active); tab.classList.toggle('bg-white', !active); tab.classList.toggle('text-slate-500', !active); }); document.querySelector(`[data-media-panel]`)?.classList.toggle('hidden', step !== 'media'); };
        tabs.forEach(tab => tab.addEventListener('click', () => select(tab.dataset.vehicleStep)));
    })();
</script>
