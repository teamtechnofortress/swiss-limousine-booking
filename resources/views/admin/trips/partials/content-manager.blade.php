@php
    $contentData = \App\Services\Trips\TripContentData::forTrip($trip);
    $savedCountries = $contentData['countries'] ?? [];
    $savedStops = $contentData['stops'] ?? [];
    $heroImage = $contentData['hero_image_url'] ?? null;
    $hasContentOldInput = session()->hasOldInput('content_form');
    $countryRows = $hasContentOldInput ? old('countries', []) : array_column($savedCountries, 'name');
    $stopRows = $hasContentOldInput ? old('stops', []) : $savedStops;
    if (empty($countryRows)) $countryRows = [''];
    if (empty($stopRows)) $stopRows = [['city' => '', 'country' => '']];
@endphp
<style>
    #trip-content-manager ~ details[data-trip-step="overview"],
    #trip-content-manager ~ details[data-trip-step="content"] { display: none; }

    /* Keep these overrides local to the two trip editor tabs. */
    #trip-content-manager .dynamic-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; padding: 12px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; }
    #trip-content-manager .dynamic-row input[type="text"] { flex: 1 1 180px; min-width: 0; }
    #trip-content-manager .dynamic-row input[type="file"] { flex: 1 1 220px; min-width: 0; }
    #trip-content-manager .dynamic-row .country-logo-preview {
        width: 48px !important; height: 48px !important; flex: 0 0 48px;
        object-fit: contain; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; padding: 3px;
    }
    #trip-content-manager input[type="text"],
    #trip-content-manager textarea,
    [data-trip-step="faqs"] input[type="text"],
    [data-trip-step="faqs"] textarea {
        display: block; width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff;
        padding: 10px 14px; font-size: 14px; line-height: 1.5; color: #0f172a;
        box-shadow: 0 1px 2px rgb(0 0 0 / .05); outline: none; transition: border-color .15s, box-shadow .15s;
    }
    #trip-content-manager textarea, [data-trip-step="faqs"] textarea { resize: vertical; }
    #trip-content-manager input::placeholder, #trip-content-manager textarea::placeholder,
    [data-trip-step="faqs"] input::placeholder, [data-trip-step="faqs"] textarea::placeholder { color: #94a3b8; }
    #trip-content-manager input[type="text"]:focus, #trip-content-manager textarea:focus,
    [data-trip-step="faqs"] input[type="text"]:focus, [data-trip-step="faqs"] textarea:focus {
        border-color: #0f172a; box-shadow: 0 0 0 3px rgb(15 23 42 / .1);
    }
    #trip-content-manager input[type="file"] { max-width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px; background: #fff; font-size: 12px; color: #475569; }
    #trip-content-manager input[type="file"]::file-selector-button { border: 0; border-radius: 8px; background: #f1f5f9; color: #334155; padding: 6px 10px; margin-right: 10px; font-weight: 600; cursor: pointer; }
    #trip-content-manager .remove-btn, [data-trip-step="faqs"] .remove-btn {
        display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; flex-shrink: 0;
        color: #be123c; cursor: pointer; padding: 0; background: #fff1f2; border-radius: 8px; border: 1px solid #ffe4e6; font-size: 12px; font-weight: 700;
    }
    #trip-content-manager .remove-btn:hover, [data-trip-step="faqs"] .remove-btn:hover { background: #ffe4e6; }
    #trip-content-manager button:focus-visible, [data-trip-step="faqs"] button:focus-visible { outline: 2px solid #0f172a; outline-offset: 3px; }
    #trip-content-manager .stop-card { border: 1px solid #e2e8f0; background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 1px 2px rgb(0 0 0 / .03); position: relative; }
    #trip-content-manager .stop-card > span { display: block; padding-right: 32px; color: #64748b; }
    #faqs-container > div { padding: 20px; border-radius: 12px; background: #f8fafc; box-shadow: none; }
    #faqs-container > div > .space-y-3 { padding-top: 24px; }
    @media (max-width: 639px) {
        #trip-content-manager .dynamic-row input[type="file"] { flex-basis: 100%; }
        #trip-content-manager .dynamic-row .remove-btn { margin-left: auto; }
    }
</style>

<section id="trip-content-manager" data-trip-step="content" class="space-y-6">
    <form data-trip-rich-content-form method="POST" action="{{ route('admin.trips.content', $trip) }}" enctype="multipart/form-data" class="admin-card overflow-hidden">@csrf @method('PUT')
        <input type="hidden" name="content_form" value="1">

        <div class="border-b border-slate-100 px-6 py-5">
            <p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-600">Website copy</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">Trip Content Configuration</h2>
            <p class="mt-1 text-sm text-slate-500">Fill out the sections below. The system will automatically generate the HTML layout when you save.</p>
        </div>

        <div class="space-y-6 p-4 sm:p-6">

            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <label class="admin-label">Hero Image</label>
                    <p class="mt-1 text-xs text-slate-500">The main visual for this trip on the frontend. It is optimized to WebP at 1920 × 800 px when saved.</p>
                    </div>
                    @if($heroImage)
                        <button type="button" id="remove-hero-image" class="remove-btn w-auto px-3" aria-label="Remove hero image">Remove image</button>
                    @endif
                </div>
                <input type="hidden" name="remove_hero_image" id="remove-hero-image-input" value="0">
                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                    <img id="hero-image-preview" src="{{ $heroImage ?: '' }}" alt="Current trip hero" class="aspect-[12/5] w-full object-cover {{ $heroImage ? '' : 'hidden' }}">
                    <div id="hero-image-placeholder" class="grid aspect-[12/5] place-items-center px-4 text-center text-sm font-medium text-slate-400 {{ $heroImage ? 'hidden' : '' }}">No hero image selected</div>
                </div>
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <input type="file" id="hero-image-input" name="hero_image" accept="image/jpeg,image/png,image/webp" class="text-sm" data-hero-image-input>
                    <p class="text-xs text-slate-500">JPEG, PNG, or WebP · Maximum 5 MB</p>
                </div>
                @error('hero_image')<p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="admin-label mb-2">Tour Overview</label>
                <textarea name="overview" rows="4" placeholder="Enter the trip overview here..." class="w-full border border-slate-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-slate-900/10">{{ old('overview', $contentData['overview'] ?? '') }}</textarea>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-5">
                <div class="mb-4 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <div><label class="admin-label">Countries Covered</label><p class="mt-1 text-xs text-slate-500">Uploaded logos are converted to optimized WebP thumbnails (maximum 240 × 160 px).</p></div>
                    <button type="button" onclick="addCountry()" class="admin-button-soft shrink-0">+ Add Country</button>
                </div>
                <div id="countries-container" class="space-y-3">
                    @foreach($countryRows as $countryIndex => $countryName)
                        @php
                            $existingCountry = $hasContentOldInput ? old("country_existing.$countryIndex") : (isset($savedCountries[$countryIndex]) ? $countryIndex : null);
                            $countryLogo = $existingCountry !== null ? ($savedCountries[$existingCountry]['logo_url'] ?? null) : null;
                        @endphp
                        <div class="dynamic-row" data-image-upload>
                            @if($existingCountry !== null)<input type="hidden" name="country_existing[{{ $countryIndex }}]" value="{{ $existingCountry }}">@endif
                            <input type="text" name="countries[{{ $countryIndex }}]" value="{{ $countryName }}" placeholder="Country Name">
                            <img data-image-preview src="{{ $countryLogo ?: '' }}" alt="{{ $countryName }} logo preview" class="country-logo-preview {{ $countryLogo ? '' : 'hidden' }}">
                            <input data-image-input type="file" name="country_logos[{{ $countryIndex }}]" class="text-sm" accept="image/jpeg,image/png,image/webp">
                            <button type="button" class="remove-btn" aria-label="Remove country" onclick="this.parentElement.remove()">X</button>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-5">
                <div class="mb-4 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <div><label class="admin-label">The Incredible Stops</label><p class="mt-1 text-xs text-slate-500">Uploaded images are converted to optimized 960 × 640 px WebP thumbnails.</p></div>
                    <button type="button" onclick="addStop()" class="admin-button-soft shrink-0">+ Add Stop</button>
                </div>
                <div id="stops-container" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($stopRows as $stopIndex => $stop)
                    @php
                        $existingStop = $stop['existing_index'] ?? ($hasContentOldInput ? null : (isset($savedStops[$stopIndex]) ? $stopIndex : null));
                        $stopImage = $existingStop !== null ? ($savedStops[$existingStop]['image_url'] ?? null) : null;
                    @endphp
                    <div class="stop-card">
                        @if($existingStop !== null)<input type="hidden" name="stops[{{ $stopIndex }}][existing_index]" value="{{ $existingStop }}">@endif
                        <button type="button" class="remove-btn absolute top-2 right-2" onclick="this.parentElement.remove()">X</button>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">STOP {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="mt-3 space-y-3">
                            <div>
                                <label class="admin-label mb-1">City</label>
                                <input type="text" name="stops[{{ $stopIndex }}][city]" value="{{ $stop['city'] ?? '' }}" placeholder="City (e.g. Zurich)" class="w-full border p-2 rounded text-sm">
                            </div>
                            <div>
                                <label class="admin-label mb-1">Country</label>
                                <input type="text" name="stops[{{ $stopIndex }}][country]" value="{{ $stop['country'] ?? '' }}" placeholder="Country" class="w-full border p-2 rounded text-sm">
                            </div>
                            <div data-image-upload>
                                <label class="admin-label mb-1">Stop Image</label>
                                <img data-image-preview src="{{ $stopImage ?: '' }}" alt="{{ $stop['city'] ?? 'Stop' }} preview" class="mb-2 h-20 w-32 rounded-lg border border-slate-200 object-cover {{ $stopImage ? '' : 'hidden' }}">
                                @if($stopImage)<p class="mb-2 text-xs text-slate-500">Current image is kept unless you choose a replacement.</p>@endif
                                <input data-image-input type="file" name="stops[{{ $stopIndex }}][image]" accept="image/jpeg,image/png,image/webp" class="text-sm w-full border border-slate-300 rounded p-1.5">
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-5">
                <label class="admin-label mb-2">Tour Itinerary</label>
                <p class="text-xs text-slate-500 mb-2">Format: Time - Title | Description (One per line)</p>
                <textarea name="itinerary" rows="6" placeholder="08:00 AM - Pickup | Meet your driver..." class="w-full border border-slate-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-slate-900/10">{{ old('itinerary', $contentData['itinerary'] ?? '') }}</textarea>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-5">
                <label class="admin-label mb-2">Your Travel Comfort</label>
                <input type="text" name="comfort_car" value="{{ old('comfort_car', $contentData['comfort_car'] ?? '') }}" placeholder="e.g. Mercedes-Benz V-Class" class="w-full border border-slate-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-slate-900/10 mb-2">
            </div>

            <div class="grid gap-5 rounded-xl border border-slate-200 bg-slate-50/60 p-5 md:grid-cols-2">
                <div class="min-w-0">
                    <label class="admin-label mb-2">What's Included (One per line)</label>
                    <textarea name="included_items" rows="5" placeholder="Professional Driver&#10;Bottled Water&#10;Wi-Fi" class="w-full border border-slate-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-slate-900/10">{{ old('included_items', $contentData['included_items'] ?? '') }}</textarea>
                </div>
                <div class="min-w-0">
                    <label class="admin-label mb-2">Not Included (One per line)</label>
                    <textarea name="not_included_items" rows="5" placeholder="Meals &amp; Beverages&#10;Entrance Fees&#10;Gratuities" class="w-full border border-slate-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-slate-900/10">{{ old('not_included_items', $contentData['not_included_items'] ?? '') }}</textarea>
                </div>
            </div>

        </div>

        <div class="flex justify-end border-t border-slate-100 p-5">
            <button class="admin-button">Save trip content</button>
        </div>
    </form>

</section>

<script>
    let countryCount = {{ max(array_keys($countryRows)) + 1 }};
    function addCountry() {
        const container = document.getElementById('countries-container');
        const div = document.createElement('div');
        div.className = 'dynamic-row';
        div.setAttribute('data-image-upload', '');
        div.innerHTML = `<input type="text" name="countries[${countryCount}]" placeholder="Country Name">
                         <img data-image-preview alt="Country logo preview" class="country-logo-preview hidden">
                         <input data-image-input type="file" name="country_logos[${countryCount}]" accept="image/jpeg,image/png,image/webp" class="text-sm">
                         <button type="button" class="remove-btn" onclick="this.parentElement.remove()">X</button>`;
        container.appendChild(div);
        attachImagePreview(div);
        countryCount++;
    }

    let stopCount = {{ max(array_keys($stopRows)) + 1 }};

    const heroImageInput = document.getElementById('hero-image-input');
    const heroImagePreview = document.getElementById('hero-image-preview');
    const heroImagePlaceholder = document.getElementById('hero-image-placeholder');
    const removeHeroImageInput = document.getElementById('remove-hero-image-input');

    heroImageInput?.addEventListener('change', event => {
        const file = event.target.files?.[0];
        if (!file) return;
        const url = URL.createObjectURL(file);
        heroImagePreview.src = url;
        heroImagePreview.classList.remove('hidden');
        heroImagePlaceholder.classList.add('hidden');
        removeHeroImageInput.value = '0';
    });

    document.getElementById('remove-hero-image')?.addEventListener('click', () => {
        heroImageInput.value = '';
        heroImagePreview.removeAttribute('src');
        heroImagePreview.classList.add('hidden');
        heroImagePlaceholder.classList.remove('hidden');
        removeHeroImageInput.value = '1';
    });

    function attachImagePreview(container) {
        const input = container.querySelector('[data-image-input]');
        const preview = container.querySelector('[data-image-preview]');
        if (!input || !preview) return;

        input.addEventListener('change', event => {
            const file = event.target.files?.[0];
            if (!file) return;
            if (preview.dataset.previewUrl) URL.revokeObjectURL(preview.dataset.previewUrl);
            const url = URL.createObjectURL(file);
            preview.src = url;
            preview.alt = `${file.name} preview`;
            preview.dataset.previewUrl = url;
            preview.classList.remove('hidden');
        });
    }

    document.querySelectorAll('[data-image-upload]').forEach(attachImagePreview);

    function addStop() {
        const container = document.getElementById('stops-container');
        const div = document.createElement('div');
        div.className = 'stop-card';

        const stopNumber = String(stopCount + 1).padStart(2, '0');

        div.innerHTML = `
            <button type="button" class="remove-btn absolute top-2 right-2" onclick="this.parentElement.remove()">X</button>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">STOP ${stopNumber}</span>
            <div class="mt-3 space-y-3">
                <div>
                    <label class="admin-label mb-1">City</label>
                    <input type="text" name="stops[${stopCount}][city]" placeholder="City (e.g. Zurich)" class="w-full border p-2 rounded text-sm">
                </div>
                <div>
                    <label class="admin-label mb-1">Country</label>
                    <input type="text" name="stops[${stopCount}][country]" placeholder="Country" class="w-full border p-2 rounded text-sm">
                </div>
                <div data-image-upload>
                    <label class="admin-label mb-1">Stop Image</label>
                    <img data-image-preview alt="Stop image preview" class="mb-2 hidden h-20 w-32 rounded-lg border border-slate-200 object-cover">
                    <input data-image-input type="file" name="stops[${stopCount}][image]" accept="image/jpeg,image/png,image/webp" class="text-sm w-full border border-slate-300 rounded p-1.5">
                </div>
            </div>
        `;

        container.appendChild(div);
        attachImagePreview(div);
        stopCount++;
    }
</script>
