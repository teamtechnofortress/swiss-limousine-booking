@props(['booking', 'compact' => false])

@php
    $trip = $booking->trip;
    $snapshot = $booking->trip_snapshot ?? [];
    $title = $snapshot['title'] ?? $trip?->title;
    $subtitle = $snapshot['subtitle'] ?? $trip?->subtitle;
    $summary = $snapshot['summary'] ?? $trip?->summary;
    $region = $snapshot['region'] ?? $trip?->region;
    $pricingMode = $snapshot['pricing_mode'] ?? $trip?->pricing_mode;
    $coverUrl = $trip?->getFirstMediaUrl('cover', 'thumbnail');
@endphp

@if($booking->type === 'trip' && $title)
    <article {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-indigo-100 bg-indigo-50/40']) }}>
        <div class="flex flex-col gap-5 p-5 sm:flex-row">
            @if($coverUrl)
                <div class="shrink-0 overflow-hidden rounded-xl bg-indigo-100" style="width: 15rem; max-width: 100%; height: 10rem;">
                    <img src="{{ $coverUrl }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
            @else
                <div class="grid shrink-0 place-items-center rounded-xl bg-indigo-100 text-lg font-black text-indigo-700" style="width: 15rem; max-width: 100%; height: 10rem;">T</div>
            @endif
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-bold uppercase tracking-[.13em] text-indigo-700">Trip booking</p>
                    @if($trip?->trashed())
                        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-bold text-slate-600">Archived trip</span>
                    @endif
                </div>
                <p class="mt-1 text-lg font-bold leading-6 text-slate-900">{{ $title }}</p>
                @if($subtitle)<p class="mt-0.5 text-sm font-medium text-slate-600">{{ $subtitle }}</p>@endif
                @if(! $compact && $summary)<p class="mt-3 text-sm leading-6 text-slate-600">{{ $summary }}</p>@endif
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium text-slate-600">
                    @if($region)<span>{{ $region }}</span>@endif
                    @if($pricingMode)<span>{{ str($pricingMode)->replace('_', ' ')->title() }} pricing</span>@endif
                </div>
            </div>
        </div>
        @if($trip && auth()->user()?->can('trips.manage'))
            <div class="border-t border-indigo-100 bg-white/70 px-4 py-3">
                <a href="{{ route('admin.trips.edit', $trip) }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Open current trip details →</a>
            </div>
        @endif
    </article>
@endif
