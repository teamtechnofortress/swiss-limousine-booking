<x-layouts.admin title="Settings">
    <div class="mb-7 max-w-2xl">
        <p class="text-sm leading-6 text-slate-500">Control the operating rules that power quotes, bookings, payment options, pricing and company communication. Access is limited by your role.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($groups as $group)
            <a href="{{ $group['accessible'] ? route('admin.settings.show', $group['key']) : '#' }}" @class(['admin-card group block p-5 transition', 'hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lg' => $group['accessible'], 'cursor-not-allowed opacity-50' => ! $group['accessible']]) @unless($group['accessible']) aria-disabled="true" @endunless>
                <div class="flex items-start justify-between gap-4">
                    <span class="grid size-10 place-items-center rounded-xl bg-slate-100 text-lg text-slate-700">⌘</span>
                    @if($group['accessible'])<span class="text-sm font-semibold text-slate-400 transition group-hover:text-slate-800">Open →</span>@else<span class="text-xs font-semibold text-slate-400">No access</span>@endif
                </div>
                <h2 class="mt-5 font-bold text-slate-900">{{ $group['label'] }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $group['count'] }} {{ \Illuminate\Support\Str::plural('setting', $group['count']) }}</p>
            </a>
        @empty
            <div class="admin-card col-span-full p-8 text-sm text-slate-500">Settings have not been seeded yet.</div>
        @endforelse
    </div>
</x-layouts.admin>
