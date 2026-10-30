<x-layouts.admin :title="$title">
    @php($details = [
        'fleet' => ['Fleet and vehicle records are part of the booking availability engine. The current backend exposes active units to booking operations, but it does not yet provide the protected fleet CRUD service needed for this admin screen.', 'Vehicle creation, vehicle pricing, unit registration, activation/retirement, and schedule policy editing.'],
        'locations' => ['Saved locations and routes affect quote calculation. The Google route lookup is intentionally live and not stored as a reusable Google Maps route cache.', 'Location CRUD, route overrides, and validation of allowed service-area rules.'],
        'reports' => ['Bookings retain monetary totals and VAT values, so report exports can be built from durable MySQL records.', 'Date range reports, tax summaries, CSV export and reconciliation filters.'],
        'audit' => ['Administrative operations already write activity records for sensitive changes such as settings and bookings.', 'A searchable audit reader with actor, target and date filters.'],
        'notifications' => ['Transactional booking emails are sent by the backend using the configured mail connection.', 'Template CRUD, preview rendering, audience rules and delivery history.'],
    ][$module])
    <div class="mx-auto max-w-3xl">
        <div class="admin-card overflow-hidden">
            <div class="border-b border-slate-100 bg-slate-50 px-6 py-5"><p class="text-xs font-bold uppercase tracking-[.14em] text-amber-700">Backend module required</p><h2 class="mt-2 text-xl font-bold text-slate-950">{{ $title }} is prepared, not simulated</h2></div>
            <div class="p-6"><p class="leading-7 text-slate-600">{{ $details[0] }}</p><div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-xs font-bold uppercase tracking-[.12em] text-slate-400">Next implementation scope</p><p class="mt-2 text-sm font-semibold text-slate-700">{{ $details[1] }}</p></div><p class="mt-6 text-sm text-slate-500">The navigation and permission boundary are in place, but there is no placeholder data or client-only CRUD here. This prevents staff from believing a change was saved when no matching server action exists.</p></div>
        </div>
    </div>
</x-layouts.admin>
