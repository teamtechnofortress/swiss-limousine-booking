<x-layouts.admin title="Enquiries">
    <div class="mb-7 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-indigo-600">Customer inbox</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Enquiries</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Review website requests, keep ownership clear, and move each enquiry through to a quote or close.</p>
        </div>
        <a class="admin-button-soft" href="{{ route('admin.enquiries.index', ['unread' => 1]) }}">Unread only{{ $stats['unread'] ? ' · '.$stats['unread'] : '' }}</a>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <article class="admin-card p-5"><p class="admin-label">Unread</p><p class="mt-2 text-3xl font-bold tracking-tight text-slate-950">{{ $stats['unread'] }}</p><p class="mt-1 text-sm text-slate-500">Not opened by the team yet</p></article>
        <article class="admin-card p-5"><p class="admin-label">Active</p><p class="mt-2 text-3xl font-bold tracking-tight text-slate-950">{{ $stats['active'] }}</p><p class="mt-1 text-sm text-slate-500">New, contacted, or quoted</p></article>
        <article class="admin-card p-5"><p class="admin-label">Received today</p><p class="mt-2 text-3xl font-bold tracking-tight text-slate-950">{{ $stats['today'] }}</p><p class="mt-1 text-sm text-slate-500">All enquiries submitted today</p></article>
    </div>

    <form class="admin-card mb-6 grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4" method="GET">
        <input class="admin-input !mt-0" name="q" placeholder="Reference, customer, email or subject" value="{{ $filters['q'] ?? '' }}">
        <select class="admin-input !mt-0" name="type"><option value="">All types</option>@foreach(\App\Models\Enquiry::TYPES as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ str($type)->title() }}</option>@endforeach</select>
        <select class="admin-input !mt-0" name="status"><option value="">All statuses</option>@foreach(\App\Models\Enquiry::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->title() }}</option>@endforeach</select>
        <select class="admin-input !mt-0" name="assigned_to"><option value="">All owners</option>@foreach($admins as $admin)<option value="{{ $admin->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $admin->id)>{{ $admin->name }}</option>@endforeach</select>
        <input class="admin-input !mt-0" type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="Received from">
        <input class="admin-input !mt-0" type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="Received to">
        <div class="flex flex-wrap items-center gap-3 sm:col-span-2"><label class="flex items-center gap-2 text-sm font-semibold text-slate-600"><input class="rounded border-slate-300 text-slate-900 focus:ring-slate-900" type="checkbox" name="unread" value="1" @checked(request()->boolean('unread'))> Unread</label><label class="flex items-center gap-2 text-sm font-semibold text-slate-600"><input class="rounded border-slate-300 text-slate-900 focus:ring-slate-900" type="checkbox" name="unassigned" value="1" @checked(request()->boolean('unassigned'))> Unassigned</label><button class="admin-button">Filter</button></div>
    </form>

    <div class="admin-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[900px]">
                <thead><tr><th>Enquiry</th><th>Customer</th><th>Context</th><th>Travel</th><th>Owner</th><th>Status</th><th>Received</th><th></th></tr></thead>
                <tbody>
                    @forelse($enquiries as $enquiry)
                        <tr class="{{ $enquiry->read_at === null ? 'bg-amber-50/40' : '' }}">
                            <td><div class="flex items-start gap-2"><span class="mt-1.5 size-2 shrink-0 rounded-full {{ $enquiry->read_at === null ? 'bg-amber-500' : 'bg-transparent' }}"></span><div><p class="font-bold text-slate-900">{{ $enquiry->reference }}</p><p class="mt-1 max-w-[190px] truncate text-xs text-slate-400">{{ $enquiry->subject_title ?: str($enquiry->type)->title().' enquiry' }}</p></div></div></td>
                            <td><p class="font-medium text-slate-800">{{ $enquiry->customer_name }}</p><p class="mt-1 max-w-[190px] truncate text-xs text-slate-400">{{ $enquiry->customer_email }}</p></td>
                            <td><p class="text-slate-700">{{ str($enquiry->type)->title() }}</p><p class="mt-1 max-w-[180px] truncate text-xs text-slate-400">{{ $enquiry->trip?->title ?? $enquiry->vehicle?->name ?? $enquiry->booking?->reference ?? 'General request' }}</p></td>
                            <td><p class="text-slate-700">{{ $enquiry->travel_date?->format('d M Y') ?? 'Flexible date' }}</p><p class="mt-1 text-xs text-slate-400">{{ $enquiry->passengers ? $enquiry->passengers.' passenger'.($enquiry->passengers === 1 ? '' : 's') : '—' }}</p></td>
                            <td>{{ $enquiry->assignedTo?->name ?? 'Unassigned' }}</td>
                            <td><x-admin.status :value="$enquiry->status" /></td>
                            <td><p class="text-slate-700">{{ $enquiry->created_at?->setTimezone('Europe/Zurich')->format('d M Y') }}</p><p class="mt-1 text-xs text-slate-400">{{ $enquiry->created_at?->setTimezone('Europe/Zurich')->format('H:i') }}</p></td>
                            <td><a class="font-semibold text-slate-700 hover:text-slate-950" href="{{ route('admin.enquiries.show', $enquiry) }}">Open →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-16 text-center"><p class="font-semibold text-slate-700">No enquiries match these filters.</p><p class="mt-1 text-sm text-slate-400">New website contact, trip, vehicle, and booking requests will appear here.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-4">{{ $enquiries->links() }}</div>
    </div>
</x-layouts.admin>
