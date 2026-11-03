<style>#trip-availability-manager ~ details[data-trip-step="availability"] { display: none; }</style>

<section id="trip-availability-manager" data-trip-step="availability" class="admin-card overflow-hidden">
    <form method="POST" action="{{ route('admin.trips.blackouts', $trip) }}">
        @csrf @method('PUT')
        <div class="border-b border-slate-100 px-6 py-5"><p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-600">Availability</p><h2 class="mt-1 text-lg font-bold text-slate-900">Blackout dates</h2><p class="mt-1 text-sm text-slate-500">Prevent this trip from being booked on dates it is unavailable.</p></div>
        <div class="p-6"><div class="space-y-3">@forelse($trip->blackouts as $index => $blackout)<div class="grid gap-3 sm:grid-cols-[220px_minmax(0,1fr)]"><input class="admin-input !mt-0" required type="date" name="blackouts[{{ $index }}][date]" value="{{ $blackout->date->toDateString() }}"><input class="admin-input !mt-0" name="blackouts[{{ $index }}][reason]" value="{{ $blackout->reason }}" placeholder="Internal reason (optional)"></div>@empty<div class="grid gap-3 sm:grid-cols-[220px_minmax(0,1fr)]"><input class="admin-input !mt-0" required type="date" name="blackouts[0][date]"><input class="admin-input !mt-0" name="blackouts[0][reason]" placeholder="Internal reason (optional)"></div>@endforelse</div></div>
        <div class="flex justify-end border-t border-slate-100 p-5"><button class="admin-button">Save blackout dates</button></div>
    </form>
</section>
