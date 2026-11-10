<!DOCTYPE html>
<html lang="en" class="bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}Swiss Limousine Admin</title>
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="stylesheet" href="{{ asset('css/admin-fallback.css') }}">
    @endif
</head>
<body class="min-h-screen bg-[#f6f7fb] font-sans text-slate-900 antialiased">
<div class="admin-shell min-h-screen lg:grid lg:grid-cols-[272px_1fr]">
    <aside class="admin-sidebar hidden min-h-screen flex-col bg-slate-950 px-4 py-6 text-slate-300 lg:flex">
        <a href="{{ route('admin.dashboard') }}" class="mb-10 flex items-center gap-3 px-3 text-white">
            <span class="grid size-10 place-items-center rounded-xl bg-amber-400 text-lg font-black text-slate-950">SL</span>
            <span><span class="block text-sm font-semibold tracking-wide">SWISS LIMOUSINE</span><span class="text-xs text-slate-500">Operations console</span></span>
        </a>
        <nav class="space-y-1 text-sm">
            <x-admin.nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" icon="grid">Overview</x-admin.nav-link>
            @can('bookings.view')<x-admin.nav-link :href="route('admin.bookings.index')" :active="request()->routeIs('admin.bookings.*')" icon="calendar">Bookings</x-admin.nav-link>@endcan
            @can('enquiries.view')<x-admin.nav-link :href="route('admin.enquiries.index')" :active="request()->routeIs('admin.enquiries.*')" icon="mail">Enquiries</x-admin.nav-link>@endcan
            @can('payments.view')<x-admin.nav-link :href="route('admin.payments.index')" :active="request()->routeIs('admin.payments.*')" icon="card">Payments</x-admin.nav-link>@endcan
            <p class="px-3 pt-7 pb-2 text-[10px] font-bold uppercase tracking-[.18em] text-slate-600">Configuration</p>
            @can('fleet.manage')<x-admin.nav-link :href="route('admin.vehicles.index')" :active="request()->routeIs('admin.vehicles.*')" icon="car">Vehicles</x-admin.nav-link>@endcan
            @can('locations.manage')<x-admin.nav-link :href="route('admin.locations.index')" :active="request()->routeIs('admin.locations.*')" icon="pin">Locations</x-admin.nav-link>@endcan
            @can('trips.manage')<x-admin.nav-link :href="route('admin.trips.index')" :active="request()->routeIs('admin.trips.*')" icon="calendar">Trips</x-admin.nav-link>@endcan
            <x-admin.nav-link :href="route('admin.settings.index')" :active="request()->routeIs('admin.settings.*')" icon="sliders">Settings</x-admin.nav-link>
            @can('integrations.view')<x-admin.nav-link :href="route('admin.mail.show')" :active="request()->routeIs('admin.mail.*')" icon="mail">Mail delivery</x-admin.nav-link>@endcan
            {{-- <p class="px-3 pt-7 pb-2 text-[10px] font-bold uppercase tracking-[.18em] text-slate-600">Administration</p>
            @can('users.manage')<x-admin.nav-link :href="route('admin.team.index')" :active="request()->routeIs('admin.team.*')" icon="users">Team</x-admin.nav-link><x-admin.nav-link :href="route('admin.roles.index')" :active="request()->routeIs('admin.roles.*')" icon="shield">Roles & access</x-admin.nav-link>@endcan
            @can('reports.tax.view')<x-admin.nav-link :href="route('admin.workspace', 'reports')" :active="request('module') === 'reports'" icon="chart">Reports</x-admin.nav-link>@endcan
            @can('audit.view')<x-admin.nav-link :href="route('admin.workspace', 'audit')" :active="request('module') === 'audit'" icon="history">Audit log</x-admin.nav-link>@endcan --}}
        </nav>
        <div class="mt-auto border-t border-slate-800 pt-4">
            <div class="flex items-center gap-3 px-3 py-2"><span class="grid size-8 place-items-center rounded-full bg-slate-800 text-xs font-bold text-amber-300">{{ str(auth()->user()->name)->substr(0, 1) }}</span><div class="min-w-0"><p class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-500">{{ auth()->user()->getRoleNames()->first() }}</p></div></div>
            <form method="POST" action="{{ route('admin.logout') }}" class="mt-2">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-400 transition hover:bg-slate-900 hover:text-white">Sign out</button></form>
        </div>
    </aside>
    <main class="admin-main min-w-0">
        <header class="admin-header sticky top-0 z-10 flex h-18 items-center justify-between border-b border-slate-200/80 bg-[#f6f7fb]/90 px-5 backdrop-blur lg:px-10">
            <div class="flex min-w-0 items-center gap-3">
                <details class="relative lg:hidden">
                    <summary class="grid size-9 cursor-pointer place-items-center rounded-lg bg-white text-slate-700 shadow-sm ring-1 ring-slate-200">☰</summary>
                    <nav class="absolute left-0 z-20 mt-3 w-64 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                        <a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Overview</a>
                        @can('bookings.view')<a href="{{ route('admin.bookings.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Bookings</a>@endcan
                        @can('enquiries.view')<a href="{{ route('admin.enquiries.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Enquiries</a>@endcan
                        @can('payments.view')<a href="{{ route('admin.payments.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Payments</a>@endcan
                        @can('fleet.manage')<a href="{{ route('admin.vehicles.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vehicles</a>@endcan
                        @can('locations.manage')<a href="{{ route('admin.locations.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Locations</a>@endcan
                        @can('trips.manage')<a href="{{ route('admin.trips.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Trips</a>@endcan
                        <a href="{{ route('admin.settings.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Settings</a>
                        @can('users.manage')<a href="{{ route('admin.team.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Team</a>@endcan
                        <form method="POST" action="{{ route('admin.logout') }}" class="mt-1 border-t border-slate-100 pt-1">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-500 hover:bg-slate-50">Sign out</button></form>
                    </nav>
                </details>
                <div><p class="text-xs font-semibold uppercase tracking-[.16em] text-slate-400">Swiss Limousine</p><h1 class="truncate text-lg font-bold text-slate-950">{{ $title ?? 'Overview' }}</h1></div>
            </div>
            <div class="flex items-center gap-3"><span class="hidden rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-500 sm:block">{{ now('Europe/Zurich')->format('D, d M') }}</span>@can('enquiries.view')<a href="{{ route('admin.enquiries.index', ['unread' => 1]) }}" title="Unread enquiries" class="grid size-9 place-items-center rounded-full bg-white text-amber-600 shadow-sm ring-1 ring-slate-200 transition hover:text-amber-700">●</a>@else @can('bookings.view')<a href="{{ route('admin.bookings.index', ['unseen' => 1]) }}" title="Unseen bookings" class="grid size-9 place-items-center rounded-full bg-white text-slate-500 shadow-sm ring-1 ring-slate-200 transition hover:text-slate-900">●</a>@endcan @endcan</div>
        </header>
        <div class="admin-content mx-auto max-w-7xl px-5 py-7 lg:px-10">
            @if(session('status'))<div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><p class="font-semibold">Please review the highlighted fields.</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
