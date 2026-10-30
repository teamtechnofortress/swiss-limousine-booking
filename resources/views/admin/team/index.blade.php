<x-layouts.admin title="Team">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Only active staff with a role can access this admin console.</p>
        <a href="{{ route('admin.roles.index') }}" class="admin-button-soft">Review roles & access →</a>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="admin-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead><tr><th>Admin user</th><th>Roles</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td><p class="font-bold text-slate-900">{{ $user->name }}</p><p class="mt-1 text-xs text-slate-400">{{ $user->email }}</p></td>
                                <td><div class="flex flex-wrap gap-1">@foreach($user->getRoleNames() as $role)<span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ $roles[$role]['label'] ?? str($role)->replace('_', ' ')->title() }}</span>@endforeach</div></td>
                                <td class="whitespace-nowrap text-sm">{{ $user->last_login_at?->setTimezone('Europe/Zurich')->format('d M Y, H:i') ?? 'Never' }}</td>
                                <td><x-admin.status :value="$user->is_active ? 'active' : 'inactive'" /></td>
                                <td><details class="relative"><summary class="cursor-pointer font-semibold text-slate-700">Manage</summary>
                                    <div class="absolute right-0 z-10 mt-2 w-80 space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-xl">
                                        <form method="POST" action="{{ route('admin.team.update', $user) }}" class="space-y-3">@csrf @method('PUT')
                                            <p class="text-xs font-bold uppercase tracking-[.12em] text-slate-400">Profile</p>
                                            <input class="admin-input !mt-0" name="name" required value="{{ $user->name }}" aria-label="Name">
                                            <input class="admin-input !mt-0" type="email" name="email" required value="{{ $user->email }}" aria-label="Email">
                                            <input class="admin-input !mt-0" type="password" name="password" minlength="12" placeholder="New password (optional)" autocomplete="new-password">
                                            <input class="admin-input !mt-0" type="password" name="password_confirmation" minlength="12" placeholder="Confirm new password" autocomplete="new-password">
                                            <button class="admin-button w-full">Save profile</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.team.roles', $user) }}" class="space-y-2 border-t border-slate-100 pt-4">@csrf @method('PUT')
                                            <p class="text-xs font-bold uppercase tracking-[.12em] text-slate-400">Roles</p>
                                            @foreach($roles as $key => $role)<label class="flex items-center gap-2 text-sm text-slate-700"><input class="rounded border-slate-300 text-slate-950 focus:ring-slate-900" type="checkbox" name="roles[]" value="{{ $key }}" @checked($user->hasRole($key))> {{ $role['label'] }}</label>@endforeach
                                            <button class="admin-button w-full">Save roles</button>
                                        </form>
                                        @if($user->id !== auth()->id())<form method="POST" action="{{ route('admin.team.status', $user) }}" class="border-t border-slate-100 pt-4">@csrf<button class="w-full text-sm font-semibold {{ $user->is_active ? 'text-rose-700' : 'text-emerald-700' }}">{{ $user->is_active ? 'Deactivate access' : 'Restore access' }}</button></form>@endif
                                    </div>
                                </details></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-16 text-center text-sm text-slate-400">No admin users have been created.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-4">{{ $users->links() }}</div>
        </div>

        <form method="POST" action="{{ route('admin.team.store') }}" class="admin-card h-fit p-6">@csrf
            <h2 class="font-bold text-slate-900">Add admin user</h2><p class="mt-1 text-sm text-slate-500">A 12-character password is required. The staff member can update it later.</p>
            <div class="mt-5 space-y-4">
                <div><label class="admin-label">Name</label><input class="admin-input" required maxlength="120" name="name" value="{{ old('name') }}"></div>
                <div><label class="admin-label">Email</label><input class="admin-input" required type="email" maxlength="190" name="email" value="{{ old('email') }}"></div>
                <div><label class="admin-label">Temporary password</label><input class="admin-input" required type="password" minlength="12" name="password" autocomplete="new-password"></div>
                <div><label class="admin-label">Confirm password</label><input class="admin-input" required type="password" minlength="12" name="password_confirmation" autocomplete="new-password"></div>
                <fieldset><legend class="admin-label">Roles</legend><div class="mt-2 space-y-2">@foreach($roles as $key => $role)<label class="flex gap-2 text-sm text-slate-700"><input class="mt-0.5 rounded border-slate-300 text-slate-950 focus:ring-slate-900" type="checkbox" name="roles[]" value="{{ $key }}" @checked(in_array($key, old('roles', []), true))><span><strong>{{ $role['label'] }}</strong><span class="mt-0.5 block text-xs text-slate-500">{{ $role['description'] }}</span></span></label>@endforeach</div></fieldset>
            </div>
            <button class="admin-button mt-6 w-full">Create admin user</button>
        </form>
    </div>
</x-layouts.admin>
