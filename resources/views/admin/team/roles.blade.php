<x-layouts.admin title="Roles & access">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3"><p class="max-w-2xl text-sm leading-6 text-slate-500">Roles are defined in the application’s RBAC catalog and synced into the database. This screen is intentionally read-only: changing permissions requires code review and a deliberate deployment.</p><a href="{{ route('admin.team.index') }}" class="admin-button-soft">Manage team →</a></div>
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach($roles as $key => $role)
            <article class="admin-card p-6">
                <div class="flex items-start justify-between gap-3"><div><h2 class="font-bold text-slate-900">{{ $role['label'] }}</h2><p class="mt-1 text-sm text-slate-500">{{ $role['description'] }}</p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500">{{ $key }}</span></div>
                <div class="mt-5 border-t border-slate-100 pt-4">
                    @php($grants = $role['permissions'] ?? [])
                    @if(in_array('*', $grants, true))<p class="text-sm font-semibold text-amber-700">All permissions</p>
                    @else
                        <div class="flex flex-wrap gap-2">@foreach($permissionGroups as $group => $permissions)@foreach($permissions as $permission => $label)@if(in_array($permission, $grants, true))<span class="rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ $label }}</span>@endif@endforeach@endforeach</div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</x-layouts.admin>
