@php
    $pickupLocations = $trip->tripLocations->where('role', 'pickup')->pluck('location')->filter(fn ($location) => $location?->is_active)->values();
    $activeVehicles = $vehicles->filter(fn ($vehicle) => $vehicle->is_active && $vehicle->category?->is_active)->values();
    $priceMap = $trip->prices->keyBy(fn ($price) => $price->pickup_location_id.':'.$price->vehicle_id);
    $fixedPricingAvailable = $trip->pickup_locations_only && $pickupLocations->isNotEmpty();
    $pricingModes = ['distance' => ['Route-based', 'Calculate the live return route using each vehicle’s km rate and minimum fare.'], 'enquiry' => ['Enquiry only', 'Show no online price; customers send a request instead.']];
    if ($fixedPricingAvailable) $pricingModes = ['fixed' => ['Fixed by pickup', 'Set one exact price for each pickup location and vehicle.'], ...$pricingModes];
    $currentPricingMode = old('pricing_mode', $trip->pricing_mode);
    if (! array_key_exists($currentPricingMode, $pricingModes)) $currentPricingMode = 'distance';
@endphp

<style>#trip-pricing-manager ~ details[data-trip-step="pricing"], details[data-trip-step="availability"] { display: none; }</style>

<section id="trip-pricing-manager" data-trip-step="pricing" data-trip-pricing-manager data-estimates-url="{{ route('admin.trips.pricing.estimates', $trip) }}" class="space-y-6">
    <form method="POST" action="{{ route('admin.trips.pricing', $trip) }}" class="admin-card overflow-hidden">
        @csrf @method('PUT')
        <div class="border-b border-slate-100 px-6 py-5"><p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-600">Trip pricing</p><h2 class="mt-1 text-lg font-bold text-slate-900">How should this trip be priced?</h2><p class="mt-1 text-sm text-slate-500">Choose one clear pricing rule. Route calculations always cover the return drive from the customer pickup to the trip destination and back.</p></div>
        <div class="space-y-6 p-6">
            <div class="grid gap-2 {{ $fixedPricingAvailable ? 'lg:grid-cols-3' : 'sm:grid-cols-2' }}">
                @foreach($pricingModes as $mode => [$label, $description])
                    <label class="cursor-pointer rounded-xl border px-4 py-3 transition has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50"><input class="sr-only" type="radio" name="pricing_mode" value="{{ $mode }}" @checked($currentPricingMode === $mode)><span class="block text-sm font-bold text-slate-900">{{ $label }}</span><span class="mt-1 block text-xs leading-5 text-slate-600">{{ $description }}</span></label>
                @endforeach
            </div>

            @if($fixedPricingAvailable)
            <section data-pricing-mode-panel="fixed" class="space-y-5">
                @if(! $trip->pickup_locations_only || $pickupLocations->isEmpty())
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950"><strong>Fixed pricing needs approved pickup locations.</strong> Go to Locations, enable “Use selected locations for pickup only”, then select the pickup locations you want to price.</div>
                @elseif($activeVehicles->isEmpty())
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950">Add an active vehicle before configuring trip prices.</div>
                @else
                    <div class="flex flex-col justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center"><div><p class="font-bold text-slate-900">Fixed price setup</p><p class="mt-1 text-sm text-slate-600">Enable only the vehicle fares customers can book. Live estimates are a recommendation, not the published price.</p></div><button type="button" data-refresh-route-estimates class="admin-button-soft whitespace-nowrap">Update route estimates</button></div>
                    <div data-route-estimate-status class="flex items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-sm leading-6 text-indigo-900"><span class="mt-2 size-1.5 shrink-0 rounded-full bg-indigo-500"></span><span><strong>Return journey included.</strong> Every distance, time and estimated fare below covers pickup → primary destination → pickup.</span></div>
                    @php($index = 0)
                    @foreach($pickupLocations as $pickup)
                        <article data-pricing-pickup="{{ $pickup->id }}" class="overflow-hidden rounded-xl border border-slate-200">
                            <header class="flex flex-col justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center"><div><h3 class="font-bold text-slate-900">{{ $pickup->name }}</h3><p class="mt-1 text-sm text-slate-500">{{ $pickup->formatted_address }}</p></div><div class="rounded-lg bg-slate-100 px-3 py-2 text-sm"><p class="text-[11px] font-bold uppercase tracking-[.12em] text-slate-500">Return drive</p><p data-route-summary="{{ $pickup->id }}" class="mt-0.5 font-bold text-slate-800">Not calculated</p></div></header>
                            <div class="overflow-x-auto"><table class="min-w-[760px] w-full text-left"><thead class="border-b border-slate-100 bg-slate-50 text-[11px] uppercase tracking-[.12em] text-slate-500"><tr><th class="px-5 py-3 font-bold">Vehicle</th><th class="px-4 py-3 font-bold">Capacity</th><th class="px-4 py-3 font-bold">Offer</th><th class="px-4 py-3 font-bold">Live return estimate</th><th class="px-5 py-3 font-bold">Published fixed fare</th></tr></thead><tbody class="divide-y divide-slate-100">
                                @foreach($activeVehicles as $vehicle)
                                    @php($price = $priceMap->get($pickup->id.':'.$vehicle->id))
                                    <tr data-pricing-vehicle><td class="px-5 py-4"><p class="font-semibold text-slate-900">{{ $vehicle->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $vehicle->price_per_km }} rappen/km · minimum fare applied</p></td><td class="px-4 py-4 text-sm text-slate-600">{{ $vehicle->passenger_capacity }} passengers</td><td class="px-4 py-4"><label class="inline-flex cursor-pointer items-center gap-2 text-sm font-bold text-indigo-700"><input type="hidden" name="prices[{{ $index }}][is_active]" value="0"><input data-price-offer type="checkbox" name="prices[{{ $index }}][is_active]" value="1" @checked($price?->is_active)> Offer</label></td><td class="px-4 py-4"><p data-route-estimate="{{ $pickup->id }}:{{ $vehicle->id }}" class="font-bold text-slate-700">Not calculated</p><p class="mt-1 text-xs text-slate-500">Full return route</p></td><td class="px-5 py-4"><input type="hidden" name="prices[{{ $index }}][pickup_location_id]" value="{{ $pickup->id }}"><input type="hidden" name="prices[{{ $index }}][vehicle_id]" value="{{ $vehicle->id }}"><input data-fixed-price class="admin-input !mt-0 w-36" type="number" min="0" name="prices[{{ $index }}][price_minor]" value="{{ $price?->price_minor }}" placeholder="42500"><p class="mt-1 text-xs text-slate-500">rappen · CHF 425.00</p></td></tr>
                                    @php($index++)
                                @endforeach
                            </tbody></table></div>
                        </article>
                    @endforeach
                @endif
            </section>
            @endif

            <section data-pricing-mode-panel="distance" class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-5"><h3 class="font-bold text-indigo-950">Live return-route pricing</h3><p class="mt-2 max-w-3xl text-sm leading-6 text-indigo-900">At quote time, the system asks Google for the drive from the chosen pickup to this trip’s primary destination, doubles the distance and drive time for the return, then applies each vehicle’s per-km rate and minimum fare. The resulting return travel time is included in vehicle availability.</p><p class="mt-3 text-sm font-semibold text-indigo-900">A primary destination is {{ $trip->tripLocations->firstWhere('role', 'main_destination')?->location?->name ?? 'not configured yet' }}.</p></section>

            <section data-pricing-mode-panel="enquiry" class="rounded-2xl border border-slate-200 bg-slate-50 p-5"><h3 class="font-bold text-slate-900">Enquiry-only trip</h3><p class="mt-2 text-sm leading-6 text-slate-600">Customers can submit their trip details for a custom quotation. No price or vehicle choice is shown online.</p></section>

            @include('admin.trips.partials.pricing-addons', ['trip' => $trip])
        </div>
        <div class="flex justify-end border-t border-slate-100 p-5"><button class="admin-button">Save pricing</button></div>
    </form>
</section>

<script>
(() => {
    const root=document.querySelector('[data-trip-pricing-manager]');if(!root)return;const modes=[...root.querySelectorAll('input[name="pricing_mode"]')],panels=[...root.querySelectorAll('[data-pricing-mode-panel]')],offers=[...root.querySelectorAll('[data-price-offer]')],refresh=root.querySelector('[data-refresh-route-estimates]'),status=root.querySelector('[data-route-estimate-status]'),addons=root.querySelector('[data-pricing-addons]'),addonList=root.querySelector('[data-addon-list]'),addonEmpty=root.querySelector('[data-addon-empty]'),addonTemplate=root.querySelector('[data-addon-template]'),addAddon=root.querySelector('[data-add-addon]');
    const sync=()=>{const mode=modes.find(input=>input.checked)?.value,onlineMode=['fixed','distance'].includes(mode);panels.forEach(panel=>panel.hidden=panel.dataset.pricingModePanel!==mode);if(addons){addons.hidden=!onlineMode;addons.querySelectorAll('[data-addon-input]').forEach(input=>input.disabled=!onlineMode);}offers.forEach(offer=>{const price=offer.closest('[data-pricing-vehicle]')?.querySelector('[data-fixed-price]');if(!price)return;price.disabled=false;price.required=offer.checked;});};
    modes.forEach(input=>input.addEventListener('change',sync));offers.forEach(input=>input.addEventListener('change',sync));root.querySelectorAll('[data-fixed-price]').forEach(price=>price.addEventListener('input',()=>{if(price.value==='')return;const offer=price.closest('[data-pricing-vehicle]')?.querySelector('[data-price-offer]');if(offer&&!offer.checked){offer.checked=true;sync();}}));sync();
    refresh?.addEventListener('click',async()=>{refresh.disabled=true;refresh.textContent='Calculating…';status.textContent='Calculating the complete return route from Google…';try{const response=await fetch(root.dataset.estimatesUrl,{headers:{Accept:'application/json'}}),payload=await response.json();if(!response.ok)throw new Error(payload.message||'Could not load estimates.');payload.data.forEach(row=>{const summary=root.querySelector(`[data-route-summary="${row.pickup_location_id}"]`);if(summary)summary.textContent=`${row.distance_km} km · ${row.duration_minutes} min`;Object.entries(row.vehicle_prices).forEach(([vehicleId,amount])=>{const item=root.querySelector(`[data-route-estimate="${row.pickup_location_id}:${vehicleId}"]`);if(item)item.textContent=`CHF ${(Number(amount)/100).toFixed(2)}`;});});status.innerHTML='<span class="mt-2 size-1.5 shrink-0 rounded-full bg-emerald-500"></span><span><strong>Live estimates updated.</strong> Every distance, drive time and estimated fare includes pickup → primary destination → pickup.</span>';}catch(error){status.textContent=error.message;}finally{refresh.disabled=false;refresh.textContent='Update route estimates';}});
    const syncAddons=()=>{if(addonEmpty)addonEmpty.classList.toggle('hidden',addonList?.querySelectorAll('[data-addon-row]').length>0);};
    const removeAddon=row=>{row.remove();syncAddons();};
    addonList?.querySelectorAll('[data-remove-addon]').forEach(button=>button.addEventListener('click',()=>removeAddon(button.closest('[data-addon-row]'))));
    addAddon?.addEventListener('click',()=>{const row=addonTemplate.content.firstElementChild.cloneNode(true),index=Date.now();row.querySelectorAll('[data-addon-field]').forEach(field=>field.name=`addons[${index}][${field.dataset.addonField}]`);row.querySelector('[data-remove-addon]').addEventListener('click',()=>removeAddon(row));addonList.append(row);syncAddons();row.querySelector('input')?.focus();});
    syncAddons();
})();
</script>
