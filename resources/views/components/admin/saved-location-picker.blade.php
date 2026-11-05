@props([
    'inputName',
    'selected' => [],
    'multiple' => false,
    'placeholder' => 'Search saved locations',
    'help' => null,
    'disabled' => false,
])

@php
    $pickerId = $attributes->get('id') ?: 'saved-location-picker-'.str($inputName)->replace(['[', ']'], '-')->trim('-');
    $selectedLocations = collect($selected)->filter()->map(fn ($location) => [
        'id' => $location->id,
        'name' => $location->name,
        'address' => $location->formatted_address,
        'is_active' => (bool) $location->is_active,
    ])->values();
@endphp

<div
    data-saved-location-picker
    data-input-name="{{ $inputName }}"
    data-multiple="{{ $multiple ? 'true' : 'false' }}"
    data-selected='@json($selectedLocations)'
    data-search-url="{{ route('admin.locations.saved-search') }}"
    @if($disabled) aria-disabled="true" @endif
>
    <div class="relative">
        <input
            id="{{ $pickerId }}"
            data-saved-location-search
            class="admin-input"
            type="search"
            autocomplete="off"
            placeholder="{{ $placeholder }}"
            aria-label="{{ $placeholder }}"
            aria-autocomplete="list"
            aria-expanded="false"
            aria-controls="{{ $pickerId }}_results"
            @disabled($disabled)
        >
        <div data-saved-location-results id="{{ $pickerId }}_results" class="absolute z-10 mt-1 hidden max-h-60 w-full overflow-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl" role="listbox"></div>
    </div>
    <p data-saved-location-status class="mt-2 hidden text-xs font-medium" aria-live="polite"></p>
    <div data-saved-location-selected class="mt-3 flex flex-wrap gap-2"></div>
    <div data-saved-location-inputs></div>
    @if($help)<p class="mt-2 text-xs text-slate-500">{{ $help }}</p>@endif
</div>
