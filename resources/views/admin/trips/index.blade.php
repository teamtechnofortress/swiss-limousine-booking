<x-layouts.admin title="Trips">
    @php($frontendUrl = rtrim((string) config('limo.frontend_url'), '/'))

    <div class="mb-7 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-indigo-600">Day tours</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Trips</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Fixed-departure tours that use the same vehicle availability protection as transfers.</p>
        </div>
        <a href="{{ route('admin.trips.create') }}" class="admin-button">+ Create trip</a>
    </div>

    <div class="admin-card overflow-hidden">
        <div class="flex flex-col justify-between gap-3 border-b border-slate-100 p-4 lg:flex-row">
            <div class="flex gap-2 text-sm font-semibold">
                <a class="rounded-lg px-3 py-2 {{ ($filters['status'] ?? '') === 'all' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600' }}" href="{{ route('admin.trips.index', ['status' => 'all']) }}">All</a>
                @foreach(['draft' => 'Drafts', 'published' => 'Published', 'archived' => 'Archived'] as $key => $label)
                    <a class="rounded-lg px-3 py-2 {{ ($filters['status'] ?? '') === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600' }}" href="{{ route('admin.trips.index', ['status' => $key]) }}">{{ $label }}</a>
                @endforeach
            </div>
            <form class="flex gap-2">
                <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                <input class="admin-input !mt-0" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search trips">
                <button class="admin-button-soft">Search</button>
            </form>
        </div>

        @if($canReorder)
            <form id="trip-reorder-form" method="POST" action="{{ route('admin.trips.reorder') }}" class="flex flex-col gap-3 border-b border-indigo-100 bg-indigo-50/60 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                @csrf
                @method('PUT')
                <p class="text-sm text-indigo-800"><span class="font-bold">Website order:</span> drag a trip by its handle, then save.</p>
                <button class="admin-button-soft">Save display order</button>
            </form>
        @endif

        <div id="trip-list" class="divide-y divide-slate-100">
            @forelse($trips as $trip)
                @php($coverUrl = $trip->getFirstMediaUrl('cover', 'thumbnail'))
                <article class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between" @if($canReorder) data-trip-row @endif>
                    @if($canReorder)<input form="trip-reorder-form" type="hidden" name="trip_ids[]" value="{{ $trip->id }}">@endif
                    <div class="flex min-w-0 items-start gap-4">
                        @if($canReorder)<span class="trip-drag-handle shrink-0 self-center select-none text-xl text-slate-300" draggable="true" data-trip-drag-handle title="Drag to reorder" aria-label="Drag to reorder">⠿</span>@endif
                        @if($coverUrl)
                            <img src="{{ $coverUrl }}" alt="" class="shrink-0 rounded-xl object-cover" style="width: 7rem; height: 5rem;">
                        @else
                            <div class="grid shrink-0 place-items-center rounded-xl bg-indigo-50 text-sm font-black text-indigo-600" style="width: 7rem; height: 5rem;">TRIP</div>
                        @endif

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-bold text-slate-900">{{ $trip->title }}</h2>
                                <span class="rounded-md px-2 py-0.5 text-xs font-bold {{ $trip->status === 'published' ? 'bg-emerald-50 text-emerald-700' : ($trip->status === 'archived' ? 'bg-slate-100 text-slate-500' : 'bg-amber-50 text-amber-700') }}">{{ ucfirst($trip->status) }}</span>
                                @if($trip->is_featured)<span class="rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700">Featured</span>@endif
                            </div>
                            @if($trip->subtitle)<p class="mt-1 text-sm font-medium text-slate-600">{{ $trip->subtitle }}</p>@endif
                            <p class="mt-1 text-sm text-slate-500">{{ $trip->region }} · {{ $trip->departures_count }} departure{{ $trip->departures_count === 1 ? '' : 's' }} · {{ $trip->prices_count }} price rows</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $frontendUrl }}/trips/{{ $trip->slug }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        @if($trip->status === 'published')
                            <a class="admin-button-soft" href="{{ $frontendUrl }}/trips/{{ $trip->slug }}" target="_blank" rel="noopener noreferrer">Open ↗</a>
                        @endif
                        <form method="POST" action="{{ route('admin.trips.status', $trip) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="return_to_list" value="1">
                            <button name="action" value="{{ $trip->status === 'published' ? 'unpublish' : 'publish' }}" class="{{ $trip->status === 'published' ? 'admin-button-soft' : 'admin-button' }}">{{ $trip->status === 'published' ? 'Unpublish' : 'Publish' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.trips.featured', $trip) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="is_featured" value="{{ $trip->is_featured ? 0 : 1 }}">
                            <button class="{{ $trip->is_featured ? 'admin-button-soft' : 'admin-button' }}">{{ $trip->is_featured ? 'Remove feature' : 'Feature trip' }}</button>
                        </form>
                        <a class="admin-button-soft" href="{{ route('admin.trips.edit', $trip) }}">Manage</a>
                    </div>
                </article>
            @empty
                <div class="p-12 text-center">
                    <p class="font-bold text-slate-800">No trips yet.</p>
                    <p class="mt-1 text-sm text-slate-500">Create a draft, add its departures and prices, then publish it when the checklist passes.</p>
                </div>
            @endforelse
        </div>
    </div>
    @if($canReorder)<script>
        (() => { const list = document.getElementById('trip-list'); let dragging; const clear = () => list?.querySelectorAll('[data-trip-row]').forEach(row => { delete row.dataset.dragging; delete row.dataset.dragOver; }); list?.addEventListener('dragstart', event => { if (!event.target.closest('[data-trip-drag-handle]')) return event.preventDefault(); dragging = event.target.closest('[data-trip-row]'); if (dragging) dragging.dataset.dragging = 'true'; }); list?.addEventListener('dragover', event => { event.preventDefault(); const target = event.target.closest('[data-trip-row]'); if (!dragging || !target || target === dragging) return; clear(); dragging.dataset.dragging = 'true'; target.dataset.dragOver = 'true'; list.insertBefore(dragging, event.clientY < target.getBoundingClientRect().top + target.offsetHeight / 2 ? target : target.nextSibling); }); list?.addEventListener('dragend', () => { dragging = null; clear(); }); })();
    </script>@endif
</x-layouts.admin>
