<style>#trip-departures-manager ~ details[data-trip-step="departures"] { display: none; }</style>

<section id="trip-departures-manager" data-trip-step="departures" data-trip-departures-manager class="admin-card overflow-hidden">
    <form method="POST" action="{{ route('admin.trips.departures', $trip) }}">
        @csrf @method('PUT')
        <div class="border-b border-slate-100 px-6 py-5"><p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-600">Departure settings</p><h2 class="mt-1 text-lg font-bold text-slate-900">When can customers leave?</h2><p class="mt-1 text-sm text-slate-500">Custom departure times are allowed by default. Turn on fixed departures only for scheduled tours.</p></div>
        <div class="space-y-6 p-6">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="cursor-pointer rounded-xl border px-4 py-4 transition has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50"><input data-departure-mode class="sr-only" type="radio" name="fixed_departures_only" value="0" @checked(! $trip->fixed_departures_only)><span class="block text-sm font-bold text-slate-900">Custom departure</span><span class="mt-1 block text-sm leading-6 text-slate-600">Customers choose their preferred departure time.</span></label>
                <label class="cursor-pointer rounded-xl border px-4 py-4 transition has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50"><input data-departure-mode class="sr-only" type="radio" name="fixed_departures_only" value="1" @checked($trip->fixed_departures_only)><span class="block text-sm font-bold text-slate-900">Fixed schedule</span><span class="mt-1 block text-sm leading-6 text-slate-600">Customers choose only from your listed departure times.</span></label>
            </div>

            <section data-custom-departures-panel class="rounded-xl border border-emerald-200 bg-emerald-50 p-5"><p class="text-xs font-bold uppercase tracking-[.12em] text-emerald-700">Custom departure</p><h3 class="mt-1 font-bold text-emerald-950">Customers choose their preferred time</h3><p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-900">Vehicle availability and minimum-notice rules are checked against the time the customer selects. No fixed schedule needs to be configured.</p></section>

            <section data-fixed-departures-panel>
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><h3 class="font-bold text-slate-900">Scheduled departure times</h3><p class="mt-1 text-sm text-slate-500">All times use the Europe/Zurich time zone.</p></div><button type="button" data-add-fixed-departure class="admin-button-soft whitespace-nowrap">+ Add departure</button></div>
                <div data-fixed-departures class="mt-4 space-y-3">
                    @forelse($trip->departures as $index => $departure)
                        <div class="grid items-end gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-[minmax(0,1fr)_auto_auto]"><div><label class="admin-label">Departure time</label><input class="admin-input !mt-1" required type="time" name="departures[{{ $index }}][start_time]" value="{{ substr($departure->start_time, 0, 5) }}"></div><label class="flex h-11 items-center gap-2 text-sm font-bold text-slate-700"><input type="hidden" name="departures[{{ $index }}][is_active]" value="0"><input type="checkbox" name="departures[{{ $index }}][is_active]" value="1" @checked($departure->is_active)> Active</label><button type="button" data-remove-fixed-departure class="h-11 rounded-lg px-3 text-sm font-bold text-rose-700 hover:bg-rose-50">Remove</button></div>
                    @empty
                        <div data-fixed-departures-empty class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">No fixed departure times yet. Add the first scheduled time above.</div>
                    @endforelse
                </div>
            </section>

        </div>
        <div class="flex items-center justify-end border-t border-slate-100 px-6 py-4"><button class="admin-button">Save departure settings</button></div>
    </form>

    @php($allPickupLinks = $trip->tripLocations->where('role', \App\Models\TripLocation::PICKUP)->values())
    @php($pickupLinks = $allPickupLinks->filter(fn ($link) => $link->location?->is_active)->values())
    @php($canConfigureDurations = $trip->pickup_locations_only && $pickupLinks->isNotEmpty() && $pickupLinks->count() === $allPickupLinks->count())
    <section class="border-t border-slate-100">
        <div class="border-b border-slate-100 px-6 py-5">
            <p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-600">Pickup duration</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">Duration by approved pickup location</h2>
            <p class="mt-1 text-sm text-slate-500">The selected pickup’s duration, plus return travel time, is reserved against vehicle availability.</p>
        </div>

        @if (! $canConfigureDurations)
            <fieldset disabled class="p-6 opacity-60" aria-describedby="pickup-duration-disabled-message">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                    <label class="admin-label">Tour duration</label>
                    <input class="admin-input !mt-1" type="number" placeholder="Minutes" disabled>
                    <p id="pickup-duration-disabled-message" class="mt-3 text-sm leading-6 text-slate-600">
                        @if (! $trip->pickup_locations_only)
                            Duration setup is disabled because “Use selected locations for pickup only” is off. Enable it and save at least one approved pickup in the Locations step first.
                        @elseif ($allPickupLinks->isEmpty())
                            Duration setup is disabled because no active approved pickup locations are selected. Add one in the Locations step first.
                        @else
                            Duration setup is disabled because one or more approved pickup locations are inactive. Reactivate or remove them in the Locations step first.
                        @endif
                    </p>
                </div>
            </fieldset>
        @else
            <form method="POST" action="{{ route('admin.trips.pickup-durations', $trip) }}" data-pickup-duration-form class="p-6">
                @csrf @method('PUT')
                <div class="space-y-3">
                    @foreach($pickupLinks as $link)
                        @php($duration = (int) ($link->duration_minutes ?? 0))
                        <div data-duration-row class="grid gap-2 rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 sm:grid-cols-[1fr_180px] sm:items-center">
                            <span><span class="block text-sm font-bold text-slate-900">{{ $link->location->name }}</span><span class="mt-1 block text-xs text-slate-600">{{ $link->location->formatted_address }}</span></span>
                            <span class="grid grid-cols-2 gap-2"><span><label class="admin-label !mt-0">Hours</label><input data-duration-hours class="admin-input !mt-1" required type="number" min="0" max="24" step="1" value="{{ intdiv($duration, 60) }}" placeholder="0"></span><span><label class="admin-label !mt-0">Minutes</label><input data-duration-minutes class="admin-input !mt-1" type="number" min="0" max="59" step="1" value="{{ $duration % 60 }}" placeholder="0"><input data-duration-total type="hidden" name="pickup_durations[{{ $link->location_id }}]" value="{{ $duration }}"></span><span class="col-span-2 text-xs text-slate-500">Minutes are optional and default to 0.</span></span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 flex justify-end"><button class="admin-button">Save pickup durations</button></div>
            </form>
        @endif
    </section>
</section>

<script>
(() => {
    const root=document.querySelector('[data-trip-departures-manager]');if(!root)return;const modes=[...root.querySelectorAll('[data-departure-mode]')],fixed=root.querySelector('[data-fixed-departures-panel]'),custom=root.querySelector('[data-custom-departures-panel]'),list=root.querySelector('[data-fixed-departures]'),add=root.querySelector('[data-add-fixed-departure]');
    const sync=()=>{const fixedOnly=modes.find(input=>input.checked)?.value==='1';fixed.hidden=!fixedOnly;custom.hidden=fixedOnly;};
    const reindex=()=>[...list.querySelectorAll('[data-fixed-departure-row]')].forEach((row,index)=>{const time=row.querySelector('input[type="time"]'),hidden=row.querySelector('input[type="hidden"]'),active=row.querySelector('input[type="checkbox"]');if(time)time.name=`departures[${index}][start_time]`;if(hidden)hidden.name=`departures[${index}][is_active]`;if(active)active.name=`departures[${index}][is_active]`;});
    const addRow=()=>{list.querySelector('[data-fixed-departures-empty]')?.remove();const row=document.createElement('div');row.dataset.fixedDepartureRow='';row.className='grid items-end gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-[minmax(0,1fr)_auto_auto]';row.innerHTML='<div><label class="admin-label">Departure time</label><input data-departure-name="start_time" class="admin-input !mt-1" required type="time"></div><label class="flex h-11 items-center gap-2 text-sm font-bold text-slate-700"><input data-departure-name="is_active" type="hidden" value="0"><input data-departure-name="is_active" type="checkbox" value="1" checked> Active</label><button type="button" data-remove-fixed-departure class="h-11 rounded-lg px-3 text-sm font-bold text-rose-700 hover:bg-rose-50">Remove</button>';list.append(row);reindex();row.querySelector('input[type="time"]').focus();};
    list.querySelectorAll(':scope > div:not([data-fixed-departures-empty])').forEach(row=>row.dataset.fixedDepartureRow='');modes.forEach(input=>input.addEventListener('change',sync));add.addEventListener('click',addRow);list.addEventListener('click',event=>{const button=event.target.closest('[data-remove-fixed-departure]');if(!button)return;button.closest('[data-fixed-departure-row]')?.remove();reindex();if(!list.querySelector('[data-fixed-departure-row]'))list.insertAdjacentHTML('beforeend','<div data-fixed-departures-empty class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">No fixed departure times yet. Add the first scheduled time above.</div>');});sync();
})();

(() => {
    const form=document.querySelector('[data-pickup-duration-form]');if(!form)return;
    const sync=row=>{const hours=row.querySelector('[data-duration-hours]'),minutes=row.querySelector('[data-duration-minutes]'),total=row.querySelector('[data-duration-total]'),value=Number(hours.value||0)*60+Number(minutes.value||0);total.value=String(value);hours.setCustomValidity(value<1?'Enter a duration of at least 1 minute.':value>1440?'Duration cannot exceed 24 hours.':'');minutes.setCustomValidity(value>1440?'Duration cannot exceed 24 hours.':'');};
    const rows=[...form.querySelectorAll('[data-duration-row]')];rows.forEach(row=>{row.querySelectorAll('[data-duration-hours],[data-duration-minutes]').forEach(input=>input.addEventListener('input',()=>sync(row)));sync(row);});form.addEventListener('submit',()=>rows.forEach(sync));
})();
</script>
