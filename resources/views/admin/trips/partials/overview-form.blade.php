<form method="POST" action="{{ $action }}" class="admin-card overflow-hidden">
    @csrf
    @if($method !== 'POST') @method($method) @endif
    <div class="grid gap-5 p-6 md:grid-cols-2">
        <div><label class="admin-label">Title</label><input class="admin-input" required name="title" value="{{ old('title', $trip?->title) }}"></div>
        <div><label class="admin-label">URL slug</label><input class="admin-input" name="slug" value="{{ old('slug', $trip?->slug) }}" placeholder="Generated from title"></div>
        <div><label class="admin-label">Subtitle</label><input class="admin-input" name="subtitle" value="{{ old('subtitle', $trip?->subtitle) }}"></div>
        <div><label class="admin-label">Region</label><input class="admin-input" required name="region" value="{{ old('region', $trip?->region) }}" placeholder="e.g. Central Switzerland"></div>
        <div class="md:col-span-2"><label class="admin-label">Card summary</label><textarea class="admin-input min-h-20" required maxlength="300" name="summary">{{ old('summary', $trip?->summary) }}</textarea></div>
        <div><label class="admin-label">SEO title</label><input class="admin-input" name="meta_title" value="{{ old('meta_title',$trip?->meta_title) }}"></div>
        <div><label class="admin-label">SEO description</label><input class="admin-input" maxlength="160" name="meta_description" value="{{ old('meta_description',$trip?->meta_description) }}"></div>
        <input type="hidden" name="traveller_limits_enabled" value="0">
        @if(old('traveller_limits_enabled', $trip?->traveller_limits_enabled ?? false))
        <section data-traveller-limits class="md:col-span-2 rounded-2xl border border-indigo-200 bg-indigo-50/50 p-5">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <button data-traveller-limits-disclosure type="button" aria-expanded="false" aria-controls="traveller-limit-fields" class="group flex min-w-0 items-start gap-3 text-left">
                    <span data-traveller-limits-chevron aria-hidden="true" class="mt-0.5 text-xl font-bold leading-5 text-indigo-500 transition-transform">›</span>
                    <span><span class="block text-xs font-bold uppercase tracking-[.16em] text-indigo-600">Passengers</span><span class="mt-1 block font-bold text-slate-900">Advanced passenger limits</span><span class="mt-1 block max-w-2xl text-sm leading-6 text-slate-600">Override the vehicle-based passenger capacity for this trip.</span></span>
                </button>
                <label class="inline-flex min-w-48 items-center justify-between gap-3 rounded-xl border border-indigo-200 bg-white px-4 py-3 text-sm font-bold text-slate-800"><span data-traveller-limits-state></span><span><input data-traveller-limits-toggle type="checkbox" name="traveller_limits_enabled" value="1" @checked(old('traveller_limits_enabled', $trip?->traveller_limits_enabled ?? false))></span></label>
            </div>
            <div id="traveller-limit-fields" data-traveller-limits-fields class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><label class="admin-label">Minimum travellers</label><input class="admin-input" required min="1" max="99" type="number" name="min_travellers" value="{{ old('min_travellers', $trip?->min_travellers ?? 1) }}"></div>
                <div><label class="admin-label">Maximum travellers</label><input class="admin-input" required min="1" max="99" type="number" name="max_travellers" value="{{ old('max_travellers', $trip?->max_travellers ?? 99) }}"></div>
            </div>
        </section>
        @endif
        <label class="flex items-center gap-3 self-end pb-3 text-sm font-semibold"><input type="hidden" name="is_featured" value="0"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured',$trip?->is_featured))> Feature on website</label>

    </div>
    <div class="flex justify-end border-t border-slate-100 p-5"><button class="admin-button">{{ $trip ? 'Save overview' : 'Create trip draft' }}</button></div>
</form>
<script>
(() => {
    const root = document.querySelector('[data-traveller-limits]');
    const toggle = root?.querySelector('[data-traveller-limits-toggle]');
    const fields = root?.querySelector('[data-traveller-limits-fields]');
    const state = root?.querySelector('[data-traveller-limits-state]');
    const disclosure = root?.querySelector('[data-traveller-limits-disclosure]');
    const chevron = root?.querySelector('[data-traveller-limits-chevron]');
    if (! root || ! toggle || ! fields || ! state || ! disclosure || ! chevron) return;
    let expanded = toggle.checked;
    const sync = () => {
        const enabled = toggle.checked;
        state.textContent = enabled ? 'Enabled' : 'Vehicle capacity';
        fields.classList.toggle('hidden', ! expanded);
        fields.classList.toggle('pointer-events-none', ! enabled);
        fields.classList.toggle('opacity-40', ! enabled);
        fields.querySelectorAll('input').forEach(input => input.disabled = ! enabled);
        disclosure.setAttribute('aria-expanded', String(expanded));
        chevron.classList.toggle('rotate-90', expanded);
    };
    disclosure.addEventListener('click', () => { expanded = ! expanded; sync(); });
    toggle.addEventListener('change', () => {
        if (! toggle.checked) {
            root.classList.add('hidden');
            return;
        }
        expanded = true;
        sync();
    });
    sync();
})();
</script>
