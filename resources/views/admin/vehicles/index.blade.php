<x-layouts.admin title="Vehicles">
    <div class="mb-7 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-indigo-600">Vehicle catalogue</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Vehicles</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">A clear view of every customer-facing vehicle, its rate and publishing state.</p>
        </div>
        <div class="flex gap-2"><a href="{{ route('admin.vehicle-categories.index') }}" class="admin-button-soft">Manage categories</a><a href="{{ route('admin.vehicles.create') }}" class="admin-button">+ Add vehicle</a></div>
    </div>

    <div class="admin-card overflow-hidden">
        <div class="flex flex-col gap-4 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-2 text-sm font-semibold">
                <a href="{{ route('admin.vehicles.index') }}" class="rounded-lg px-3 py-2 {{ empty($filters['status']) && empty($filters['category_id']) ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">All vehicles</a>
                <a href="{{ route('admin.vehicles.index', ['status' => 'active']) }}" class="rounded-lg px-3 py-2 {{ ($filters['status'] ?? '') === 'active' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">Published</a>
                <a href="{{ route('admin.vehicles.index', ['status' => 'inactive']) }}" class="rounded-lg px-3 py-2 {{ ($filters['status'] ?? '') === 'inactive' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">Drafts</a>
            </div>
            <form method="GET" class="flex w-full gap-2 lg:w-auto">
                <input class="admin-input !mt-0 min-w-0" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search vehicles">
                <select class="admin-input !mt-0 w-40" name="category_id"><option value="">All categories</option>@foreach($filterCategories as $category)<option value="{{ $category->id }}" @selected((int) ($filters['category_id'] ?? 0) === $category->id)>{{ $category->name }}</option>@endforeach</select>
                <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}"><button class="admin-button-soft">Search</button>
            </form>
        </div>

        @if($canReorder)
            <form id="vehicle-reorder-form" method="POST" action="{{ route('admin.vehicles.reorder') }}" class="flex flex-col gap-3 border-b border-indigo-100 bg-indigo-50/60 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">@csrf @method('PUT')<p class="text-sm text-indigo-800"><span class="font-bold">Website order:</span> drag a row by its handle, then save.</p><button class="admin-button-soft">Save display order</button></form>
        @endif

        <div class="hidden grid-cols-[32px_minmax(280px,1.5fr)_minmax(130px,.7fr)_130px_96px] gap-5 border-b border-slate-100 bg-slate-50 px-5 py-3 text-[11px] font-bold uppercase tracking-[.12em] text-slate-400 xl:grid">
            <span></span><span>Vehicle</span><span>Online rate</span><span>Status</span><span>Manage</span>
        </div>

        <div id="vehicle-list" class="divide-y divide-slate-100">
            @forelse($vehicles as $vehicle)
                @php($primaryImage = $vehicle->media->where('collection_name', 'images')->sortBy('order_column')->first())
                @php($primaryImagePath = $primaryImage?->hasGeneratedConversion('thumbnail') ? $primaryImage->getPathRelativeToRoot('thumbnail') : $primaryImage?->getPathRelativeToRoot())
                <article class="grid gap-4 px-5 py-5 xl:grid-cols-[32px_minmax(280px,1.5fr)_minmax(130px,.7fr)_130px_96px] xl:items-center" @if($canReorder) draggable="true" data-vehicle-row @endif>
                    @if($canReorder)<input form="vehicle-reorder-form" type="hidden" name="vehicle_ids[]" value="{{ $vehicle->id }}">@endif
                    <div class="hidden cursor-grab text-xl text-slate-300 xl:block" aria-label="Drag to reorder">⠿</div>
                    <div class="flex min-w-0 gap-4">
                        <div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200">@if($primaryImagePath)<img class="h-full w-full object-cover" src="{{ '/storage/'.ltrim($primaryImagePath, '/') }}" alt="">@else<span class="text-xs font-bold text-slate-400">No image</span>@endif</div>
                        <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h2 class="truncate font-bold text-slate-900">{{ $vehicle->name }}</h2><span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">{{ $vehicle->category->name }}</span></div><p class="mt-1 text-sm text-slate-500">{{ $vehicle->model_name ?: 'No model reference' }}</p><div class="mt-2 flex flex-wrap gap-1.5 text-xs font-semibold text-slate-500"><span class="rounded-md bg-slate-50 px-2 py-1">{{ $vehicle->passenger_capacity }} passengers</span><span class="rounded-md bg-slate-50 px-2 py-1">{{ $vehicle->luggage_capacity }} bags</span><span class="rounded-md bg-slate-50 px-2 py-1">{{ $vehicle->media->where('collection_name', 'images')->count() }} images</span></div></div>
                    </div>
                    <div><p class="text-sm font-bold text-slate-900">{{ $vehicle->price_per_km === null ? 'Price on request' : 'CHF '.number_format($vehicle->price_per_km / 100, 2).'/km' }}</p><p class="mt-1 text-xs text-slate-400">{{ $vehicle->minimum_fare === null ? 'No minimum fare' : 'Min. CHF '.number_format($vehicle->minimum_fare / 100, 2) }}</p></div>
                    <div><span class="inline-flex items-center gap-1.5 text-sm font-bold {{ $vehicle->is_active ? 'text-emerald-700' : 'text-amber-700' }}"><span class="size-2 rounded-full {{ $vehicle->is_active ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>{{ $vehicle->is_active ? 'Published' : 'Draft' }}</span><p class="mt-1 text-xs text-slate-400">Updated {{ $vehicle->updated_at->format('d M Y') }}</p></div>
                    <a href="{{ route('admin.vehicles.edit', $vehicle) }}" class="admin-button-soft text-center">Manage</a>
                </article>
            @empty
                <div class="p-12 text-center"><p class="font-bold text-slate-800">No vehicles match this view.</p><p class="mt-1 text-sm text-slate-500">Clear the filters or add a vehicle to the catalogue.</p></div>
            @endforelse
        </div>
    </div>

    @if($canReorder)<script>
        (() => { const list = document.getElementById('vehicle-list'); let dragging; list?.addEventListener('dragstart', e => dragging = e.target.closest('[data-vehicle-row]')); list?.addEventListener('dragover', e => { e.preventDefault(); const target = e.target.closest('[data-vehicle-row]'); if (!dragging || !target || target === dragging) return; list.insertBefore(dragging, e.clientY < target.getBoundingClientRect().top + target.offsetHeight / 2 ? target : target.nextSibling); }); list?.addEventListener('dragend', () => dragging = null); })();
    </script>@endif
</x-layouts.admin>
