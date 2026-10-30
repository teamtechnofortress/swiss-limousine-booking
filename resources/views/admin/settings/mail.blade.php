<x-layouts.admin title="Mail delivery">
    <div class="mb-6"><a href="{{ route('admin.settings.show', 'integrations') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">← Integrations</a><p class="mt-2 text-sm text-slate-500">SMTP credentials are encrypted at rest. A password is never displayed after it is saved.</p></div>
    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('admin.mail.update') }}" class="admin-card p-6">@csrf @method('PUT')
            <h2 class="font-bold text-slate-900">SMTP connection</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><label class="admin-label">Mailer</label><select class="admin-input" name="mailer"><option value="smtp" @selected($mail['mailer'] === 'smtp')>SMTP</option><option value="log" @selected($mail['mailer'] === 'log')>Log (local only)</option></select></div>
                <div><label class="admin-label">Encryption</label><select class="admin-input" name="encryption"><option value="tls" @selected($mail['encryption'] === 'tls')>TLS / STARTTLS</option><option value="ssl" @selected($mail['encryption'] === 'ssl')>SSL</option><option value="none" @selected($mail['encryption'] === 'none')>None</option></select></div>
                <div><label class="admin-label">SMTP host</label><input class="admin-input" name="host" value="{{ $mail['host'] }}" autocomplete="off"></div>
                <div><label class="admin-label">Port</label><input class="admin-input" type="number" min="1" max="65535" name="port" value="{{ $mail['port'] }}"></div>
                <div><label class="admin-label">Username</label><input class="admin-input" name="username" value="{{ $mail['username'] }}" autocomplete="off"></div>
                <div><label class="admin-label">Password {{ $mail['password_set'] ? '(saved)' : '' }}</label><input class="admin-input" type="password" name="password" autocomplete="new-password" placeholder="{{ $mail['password_set'] ? 'Leave blank to keep' : 'Enter password' }}"></div>
            </div>
            @can('integrations.manage')<div class="mt-6 flex justify-end"><button class="admin-button">Save connection</button></div>@endcan
        </form>
        <div class="space-y-6">
            <form method="POST" action="{{ route('admin.sender.update') }}" class="admin-card p-6">@csrf @method('PUT')
                <h2 class="font-bold text-slate-900">Sender identity</h2>
                <div class="mt-5 space-y-4"><div><label class="admin-label">From name</label><input class="admin-input" required name="from_name" value="{{ $sender['from_name'] }}"></div><div><label class="admin-label">From email</label><input class="admin-input" required type="email" name="from_address" value="{{ $sender['from_address'] }}"></div></div>
                @can('company.manage')<div class="mt-6 flex justify-end"><button class="admin-button">Save sender</button></div>@endcan
            </form>
            @can('integrations.manage')<form method="POST" action="{{ route('admin.mail.test') }}" class="admin-card p-6">@csrf
                <h2 class="font-bold text-slate-900">Send a test</h2><p class="mt-1 text-sm text-slate-500">Use this after saving the connection to verify delivery.</p>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row"><input class="admin-input !mt-0" type="email" required name="to" value="{{ auth()->user()->email }}" aria-label="Recipient email"><button class="admin-button whitespace-nowrap">Send test</button></div>
            </form>@endcan
        </div>
    </div>
</x-layouts.admin>
