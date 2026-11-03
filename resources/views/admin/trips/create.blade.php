<x-layouts.admin title="Create trip">
    <div class="mb-7"><a href="{{ route('admin.trips.index') }}" class="text-sm font-bold text-indigo-700">← Trips</a><h1 class="mt-4 text-2xl font-bold text-slate-950">Create a trip draft</h1><p class="mt-2 text-sm text-slate-500">Start with the core details. You will then add departures, pricing, content and media in the editor.</p></div>
    @include('admin.trips.partials.overview-form', ['action' => route('admin.trips.store'), 'method' => 'POST', 'trip' => null])
</x-layouts.admin>
