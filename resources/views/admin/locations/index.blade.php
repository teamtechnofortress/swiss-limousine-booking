<x-layouts.admin title="Locations">
    <div class="mb-7 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div><p class="text-xs font-bold uppercase tracking-[.16em] text-indigo-600">Booking catalogue</p><h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Locations</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Curated places for quick booking choices, pickup restriction and future location pages. Customer Google searches are never added here automatically.</p></div>
        <a href="{{ route('admin.locations.create') }}" class="admin-button">+ Add location</a>
    </div>
    <div class="admin-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between"><div class="flex flex-wrap gap-2 text-sm font-semibold"><a class="rounded-lg px-3 py-2 {{ empty($filters['state']) ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600' }}" href="{{ route('admin.locations.index') }}">All</a>@foreach(['active'=>'Active','featured'=>'Featured','allowed_pickup'=>'Allowed pickups','inactive'=>'Inactive'] as $key => $label)<a class="rounded-lg px-3 py-2 {{ ($filters['state'] ?? '') === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600' }}" href="{{ route('admin.locations.index', ['state' => $key]) }}">{{ $label }}</a>@endforeach</div><form method="GET" class="flex gap-2"><input type="hidden" name="state" value="{{ $filters['state'] ?? '' }}"><input class="admin-input !mt-0" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search places"><button class="admin-button-soft">Search</button></form></div>
        <div class="divide-y divide-slate-100">
            @forelse($locations as $location)
                <article class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-bold text-slate-900">{{ $location->name }}</h2>
                            @if (! $location->is_active)
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-500">Inactive</span>
                            @endif
                            @if ($location->is_featured)
                                <span class="rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700">Booking chip</span>
                            @endif
                            @if ($location->allowed_pickup)
                                <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">Allowed pickup</span>
                            @endif
                        </div>
                        <p class="mt-1 truncate text-sm text-slate-500">{{ $location->formatted_address }}</p>
                        <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $location->country_code }} · {{ $location->google_place_id }}</p>
                    </div>
                    <a href="{{ route('admin.locations.edit', $location) }}" class="admin-button-soft text-center">Manage</a>
                </article>
            @empty
                <div class="p-12 text-center">
                    <p class="font-bold text-slate-800">No locations yet.</p>
                    <p class="mt-1 text-sm text-slate-500">Add an airport, city or meeting point from Google to start your curated catalogue.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.admin>
