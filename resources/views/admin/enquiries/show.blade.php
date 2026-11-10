<x-layouts.admin :title="$enquiry->reference">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.enquiries.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">← Back to enquiries</a>
            <div class="mt-3 flex flex-wrap items-center gap-3"><h2 class="text-2xl font-bold tracking-tight text-slate-950">{{ $enquiry->reference }}</h2><x-admin.status :value="$enquiry->status" /><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ str($enquiry->type)->title() }}</span></div>
            <p class="mt-2 text-sm text-slate-500">Received {{ $enquiry->created_at?->setTimezone('Europe/Zurich')->format('D, d M Y · H:i') }} Zurich time</p>
        </div>
        @if($enquiry->read_at)<p class="text-sm text-slate-400">Opened {{ $enquiry->read_at->setTimezone('Europe/Zurich')->diffForHumans() }}</p>@endif
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.55fr_1fr]">
        <div class="space-y-6">
            <article class="admin-card p-6">
                <h3 class="text-base font-bold text-slate-900">Customer</h3>
                <div class="mt-5 grid gap-5 sm:grid-cols-2"><div><p class="admin-label">Name</p><p class="mt-2 font-semibold text-slate-800">{{ $enquiry->customer_name }}</p></div><div><p class="admin-label">Preferred contact</p><p class="mt-2 font-semibold text-slate-800">{{ $enquiry->preferred_contact_method ? str($enquiry->preferred_contact_method)->title() : 'Not specified' }}</p></div><div><p class="admin-label">Email</p><a class="mt-2 block font-medium text-indigo-700 hover:text-indigo-900" href="mailto:{{ $enquiry->customer_email }}">{{ $enquiry->customer_email }}</a></div><div><p class="admin-label">Phone</p>@if($enquiry->customer_phone)<a class="mt-2 block font-medium text-indigo-700 hover:text-indigo-900" href="tel:{{ $enquiry->customer_phone }}">{{ $enquiry->customer_phone }}</a>@else<p class="mt-2 text-slate-500">Not provided</p>@endif</div></div>
            </article>

            <article class="admin-card p-6">
                <h3 class="text-base font-bold text-slate-900">Message</h3>
                <div class="mt-4 whitespace-pre-wrap rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-700">{{ $enquiry->message }}</div>
            </article>

            <article class="admin-card p-6">
                <h3 class="text-base font-bold text-slate-900">Journey details</h3>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2"><div><dt class="admin-label">Travel date</dt><dd class="mt-2 font-semibold text-slate-800">{{ $enquiry->travel_date?->format('D, d M Y') ?? 'Not specified' }}</dd></div><div><dt class="admin-label">Return date</dt><dd class="mt-2 font-semibold text-slate-800">{{ $enquiry->return_date?->format('D, d M Y') ?? 'Not specified' }}</dd></div><div><dt class="admin-label">Passengers & luggage</dt><dd class="mt-2 font-semibold text-slate-800">{{ $enquiry->passengers ?? '—' }} passengers · {{ $enquiry->luggage ?? '—' }} bags</dd></div><div><dt class="admin-label">Pickup → drop-off</dt><dd class="mt-2 font-semibold text-slate-800">{{ $enquiry->pickup_location ?: 'Not specified' }} <span class="px-1 text-slate-400">→</span> {{ $enquiry->dropoff_location ?: 'Not specified' }}</dd></div></dl>
            </article>

            <article class="admin-card p-6">
                <h3 class="text-base font-bold text-slate-900">Linked context</h3>
                <div class="mt-5 space-y-3 text-sm">
                    @if($enquiry->trip)<div class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 p-4"><div><p class="admin-label">Trip</p><p class="mt-1 font-semibold text-slate-800">{{ $enquiry->trip->title }}</p></div>@can('trips.manage')<a class="font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('admin.trips.edit', $enquiry->trip) }}">Open trip →</a>@endcan</div>@endif
                    @if($enquiry->vehicle)<div class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 p-4"><div><p class="admin-label">Vehicle</p><p class="mt-1 font-semibold text-slate-800">{{ $enquiry->vehicle->name }}</p></div>@can('fleet.manage')<a class="font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('admin.vehicles.edit', $enquiry->vehicle) }}">Open vehicle →</a>@endcan</div>@endif
                    @if($enquiry->booking)<div class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 p-4"><div><p class="admin-label">Booking</p><p class="mt-1 font-semibold text-slate-800">{{ $enquiry->booking->reference }}</p></div>@can('bookings.view')<a class="font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('admin.bookings.show', $enquiry->booking) }}">Open booking →</a>@endcan</div>@endif
                    @if(! $enquiry->trip && ! $enquiry->vehicle && ! $enquiry->booking)<p class="text-slate-500">This is a general enquiry with no linked trip, vehicle, or booking.</p>@endif
                </div>
            </article>
        </div>

        <aside class="space-y-6">
            @can('enquiries.manage')
                <article class="admin-card p-5">
                    <h3 class="font-bold text-slate-900">Manage enquiry</h3>
                    <form class="mt-5 space-y-5" method="POST" action="{{ route('admin.enquiries.update', $enquiry) }}">@csrf @method('PATCH')
                        <div><label class="admin-label" for="status">Status</label><select id="status" class="admin-input" name="status">@foreach(\App\Models\Enquiry::STATUSES as $status)<option value="{{ $status }}" @selected(old('status', $enquiry->status) === $status)>{{ str($status)->title() }}</option>@endforeach</select></div>
                        <div><label class="admin-label" for="assigned_to">Owner</label><select id="assigned_to" class="admin-input" name="assigned_to"><option value="">Unassigned</option>@foreach($admins as $admin)<option value="{{ $admin->id }}" @selected((string) old('assigned_to', $enquiry->assigned_to) === (string) $admin->id)>{{ $admin->name }}</option>@endforeach</select></div>
                        <div><label class="admin-label" for="internal_notes">Private notes</label><textarea id="internal_notes" class="admin-input min-h-36" name="internal_notes" maxlength="10000" placeholder="Visible only to your team">{{ old('internal_notes', $enquiry->internal_notes) }}</textarea></div>
                        <button class="admin-button w-full">Save changes</button>
                    </form>
                </article>
            @endcan

            <article class="admin-card p-5">
                <h3 class="font-bold text-slate-900">Record</h3>
                <dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between gap-4"><dt class="text-slate-500">Reference</dt><dd class="font-semibold text-slate-800">{{ $enquiry->reference }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-500">Opened</dt><dd class="text-right font-medium text-slate-800">{{ $enquiry->read_at?->setTimezone('Europe/Zurich')->format('d M Y, H:i') ?? 'Not yet' }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-500">First handled</dt><dd class="text-right font-medium text-slate-800">{{ $enquiry->handled_at?->setTimezone('Europe/Zurich')->format('d M Y, H:i') ?? 'Not yet' }}</dd></div>@if($enquiry->source_url)<div class="border-t border-slate-100 pt-3"><dt class="text-slate-500">Source page</dt><dd class="mt-1 break-all font-medium text-slate-700">{{ $enquiry->source_url }}</dd></div>@endif</dl>
            </article>
        </aside>
    </div>
</x-layouts.admin>
