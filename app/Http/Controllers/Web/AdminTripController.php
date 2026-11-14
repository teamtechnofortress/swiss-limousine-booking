<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\Geo\GoogleMapsClient;
use App\Services\Pricing\PricingService;
use App\Services\Trips\TripManager;
use App\Services\Trips\TripContentData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Validation\ValidationException;

final class AdminTripController extends Controller
{
    public function __construct(
        private readonly TripManager $trips,
        private readonly GoogleMapsClient $maps,
        private readonly PricingService $pricing,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,published,archived']]);
        $trips = Trip::query()->withCount(['departures', 'prices'])->when($filters['q'] ?? null, fn ($q, $term) => $q->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'))->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->orderBy('sort_order')->orderBy('title')->get();
        return view('admin.trips.index', compact('trips', 'filters'));
    }
    public function create(): View { return view('admin.trips.create'); }
    public function store(Request $request): RedirectResponse { $trip = $this->trips->create($this->overview($request)); return redirect()->route('admin.trips.edit', $trip)->with('status', 'Trip draft created. Complete each checklist item before publishing.'); }
    public function edit(Trip $trip): View { return view('admin.trips.edit', ['trip' => $trip->load(['departures', 'items', 'addons', 'prices.vehicle', 'blackouts', 'tripLocations.location', 'media']), 'vehicles' => Vehicle::query()->with('category')->orderBy('name')->get(), 'checklist' => $this->trips->checklist($trip)]); }
    public function update(Trip $trip, Request $request): RedirectResponse { $this->trips->update($trip, $this->overview($request)); return $this->stepRedirect($trip, $request)->with('status', 'Trip overview saved.'); }
    public function departures(Trip $trip, Request $request): RedirectResponse { $request->validate(['fixed_departures_only' => ['required','boolean']]); $fixedOnly = $request->boolean('fixed_departures_only'); $trip->update(['fixed_departures_only' => $fixedOnly]); if ($fixedOnly) $this->trips->replaceDepartures($trip, $this->rows($request, 'departures', ['start_time' => ['required','date_format:H:i'], 'is_active' => ['nullable','boolean']])); return $this->stepRedirect($trip, $request)->with('status', $fixedOnly ? 'Fixed departure times saved.' : 'Customers can now choose a custom departure time.'); }
   public function content(Trip $trip, Request $request): RedirectResponse
{
    // 1. Validate the simple inputs
    $request->validate([
        'overview' => ['nullable', 'string', 'max:5000'],
        'hero_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        'remove_hero_image' => ['nullable', 'boolean'],
        'countries' => ['nullable', 'array'],
        'countries.*' => ['nullable', 'string', 'max:100'],
        'country_logos' => ['nullable', 'array'],
        'country_logos.*' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        'country_existing' => ['nullable', 'array'],
        'country_existing.*' => ['nullable', 'integer', 'min:0'],
        'stops' => ['nullable', 'array'],
        'stops.*' => ['array'],
        'stops.*.city' => ['nullable', 'string', 'max:255'],
        'stops.*.country' => ['nullable', 'string', 'max:100'],
        'stops.*.image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        'stops.*.existing_index' => ['nullable', 'integer', 'min:0'],
        'itinerary' => ['nullable', 'string'],
        'comfort_car' => ['nullable', 'string', 'max:255'],
        'included_items' => ['nullable', 'string'],
        'not_included_items' => ['nullable', 'string'],
    ]);

    $saved = TripContentData::forTrip($trip);
    $overview = (string) $request->input('overview', '');
    $previousHeroPath = $saved['hero_image_path'] ?? null;
    $heroImagePath = $previousHeroPath;
    $heroImageUrl = $saved['hero_image_url'] ?? null;

    if ($request->hasFile('hero_image')) {
        $heroImagePath = $this->storeOptimizedImage($request->file('hero_image'), 'trip-content/hero', 1920, 800, Fit::Crop);
        $heroImageUrl = asset('storage/'.$heroImagePath);
    } elseif ($request->boolean('remove_hero_image')) {
        $heroImagePath = null;
        $heroImageUrl = null;
    }
    $countries = [];
    foreach ($request->input('countries', []) ?? [] as $index => $name) {
        if (trim((string) $name) === '') continue;
        $existing = $request->input("country_existing.$index");
        $country = $existing !== null ? ($saved['countries'][$existing] ?? []) : [];
        $country['name'] = trim($name);
        if ($request->hasFile("country_logos.$index")) {
            $country['logo_path'] = $this->storeOptimizedImage($request->file("country_logos.$index"), 'trip-content/countries', 240, 160, Fit::Max);
            $country['logo_url'] = asset('storage/'.$country['logo_path']);
        }
        $countries[] = $country;
    }
    $stops = [];
    foreach ($request->input('stops', []) ?? [] as $index => $stop) {
        if (trim((string) ($stop['city'] ?? '')) === '') continue;
        $existing = $stop['existing_index'] ?? null;
        $savedStop = $existing !== null ? ($saved['stops'][$existing] ?? []) : [];
        $savedStop['city'] = trim($stop['city']);
        $savedStop['country'] = trim((string) ($stop['country'] ?? ''));
        if ($request->hasFile("stops.$index.image")) {
            $savedStop['image_path'] = $this->storeOptimizedImage($request->file("stops.$index.image"), 'trip-content/stops', 960, 640, Fit::Crop);
            $savedStop['image_url'] = asset('storage/'.$savedStop['image_path']);
        }
        $stops[] = $savedStop;
    }
    $itineraryRaw = (string) $request->input('itinerary', '');
    $comfortCar = (string) $request->input('comfort_car', '');
    $includedItems = (string) $request->input('included_items', '');
    $notIncludedItems = (string) $request->input('not_included_items', '');

    // 2. Build the HTML String
    $html = '';

    // --- Hero image ---
    if ($heroImageUrl) {
        $html .= '<section class="tour-hero-image-section">';
        $html .= '<img src="' . e($heroImageUrl) . '" alt="' . e($trip->title) . '" class="tour-hero-image">';
        $html .= '</section>';
    }

    // --- Overview ---
    if ($overview) {
        $html .= '<section class="tour-overview-section">';
        $html .= '<h2 class="section-title">Tour Overview</h2>';
        $html .= '<p class="section-text">' . e($overview) . '</p>';
        $html .= '</section>';
    }

    // --- Countries ---
    if (!empty($countries)) {
        $html .= '<section class="tour-countries-section">';
        $html .= '<h2 class="section-title">Countries Covered</h2>';
        $html .= '<ul class="country-flags-list">';

        $flagCodes = [
            'Switzerland' => 'ch',
            'Germany' => 'de',
            'Austria' => 'at',
            'Liechtenstein' => 'li'
        ];

        foreach ($countries as $countryData) {
            $country = $countryData['name'];
            if (empty($country)) continue;

            $code = $flagCodes[$country] ?? strtolower(substr($country, 0, 2));
            $html .= '<li class="country-pill">';
            $logoUrl = $countryData['logo_url'] ?? 'https://flagcdn.com/w20/' . $code . '.png';
            $html .= '<img src="' . e($logoUrl) . '" alt="' . e($country) . ' Flag" class="flag-icon">';
            $html .= '<span>' . e($country) . '</span>';
            $html .= '</li>';
        }
        $html .= '</ul></section>';
    }

    // --- Stops ---
    if (!empty($stops)) {
        $html .= '<section class="tour-stops-section">';
        $html .= '<h2 class="section-title">The 6 Incredible Stops</h2>';
        $html .= '<div class="stops-grid">';

        foreach ($stops as $index => $stop) {
            if (empty($stop['city'])) continue;

            $num = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            $imgUrl = $stop['image_url'] ?? '[INSERT_IMAGE_URL]';

            $html .= '<article class="stop-card">';
            $html .= '<span class="stop-number">' . $num . '</span>';
            $html .= '<img src="' . e($imgUrl) . '" alt="' . e($stop['city']) . '" class="stop-image">';
            $html .= '<h4 class="stop-city">' . e($stop['city']) . '</h4>';
            $html .= '<p class="stop-country">' . e($stop['country'] ?? '') . '</p>';
            $html .= '</article>';
        }
        $html .= '</div></section>';
    }

       // --- Itinerary ---
    if ($itineraryRaw) {
        $html .= '<section class="tour-itinerary-section">';
        $html .= '<h2 class="section-title">Your Tour Itinerary</h2>';
        $html .= '<ul class="itinerary-timeline">';

        // Split the text into blocks based on empty lines
        $blocks = preg_split('/\n\s*\n/', trim($itineraryRaw));

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') continue;

            // Split the block by the pipe symbol (|)
            $parts = explode('|', $block, 2);
            $title = trim($parts[0]);
            $desc = isset($parts[1]) ? trim($parts[1]) : '';

            $html .= '<li class="itinerary-item">';
            $html .= '<strong class="itinerary-time">' . e($title) . '</strong>';
            if ($desc) {
                $html .= '<p class="itinerary-desc">' . e($desc) . '</p>';
            }
            $html .= '</li>';
        }
        $html .= '</ul></section>';
    }
    
    // --- Comfort ---
    if ($comfortCar) {
        $html .= '<section class="tour-comfort-section">';
        $html .= '<h2 class="section-title">Your Travel Comfort</h2>';
        $html .= '<h3 class="comfort-car">' . e($comfortCar) . '</h3>';
        $html .= '<ul class="comfort-features">';
        $html .= '<li>Up to 7 Passengers</li><li>Spacious &amp; Luxurious Interior</li>';
        $html .= '<li>Climate Control</li><li>Professional Chauffeur</li>';
        $html .= '</ul></section>';
    }

    // --- Included / Not Included ---
    if ($includedItems || $notIncludedItems) {
        $html .= '<section class="tour-inclusions-section">';

        if ($includedItems) {
            $html .= '<article class="included-list"><h4 class="included-title">What\'s Included</h4><ul>';
            foreach (explode("\n", $includedItems) as $item) {
                if (trim($item)) $html .= '<li>' . e(trim($item)) . '</li>';
            }
            $html .= '</ul></article>';
        }

        if ($notIncludedItems) {
            $html .= '<article class="not-included-list"><h4 class="not-included-title">Not Included</h4><ul>';
            foreach (explode("\n", $notIncludedItems) as $item) {
                if (trim($item)) $html .= '<li>' . e(trim($item)) . '</li>';
            }
            $html .= '</ul></article>';
        }

        $html .= '</section>';
    }

    // 3. Save the generated HTML to the description column
    $trip->update([
        'description' => $html,
        'content_data' => [
            'overview' => $overview, 'hero_image_path' => $heroImagePath, 'hero_image_url' => $heroImageUrl,
            'countries' => $countries, 'stops' => $stops,
            'itinerary' => $itineraryRaw, 'comfort_car' => $comfortCar,
            'included_items' => $includedItems, 'not_included_items' => $notIncludedItems,
        ],
    ]);

    if (
        is_string($previousHeroPath)
        && $previousHeroPath !== $heroImagePath
        && str_starts_with($previousHeroPath, 'trip-content/hero/')
    ) {
        Storage::disk('public')->delete($previousHeroPath);
    }

    return $this->stepRedirect($trip, $request)->with('status', 'Trip content generated and saved successfully!');
}

    /** Store a WebP web thumbnail while keeping the original upload out of public storage. */
    private function storeOptimizedImage(UploadedFile $file, string $directory, int $width, int $height, Fit $fit): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'trip-image-');
        $path = $directory.'/'.Str::uuid().'.webp';

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary image file.');
        }

        try {
            Image::load($file->getRealPath())
                ->orientation()
                ->fit($fit, $width, $height)
                ->format('webp')
                ->quality(85)
                ->save($temporaryPath);

            Storage::disk('public')->put($path, file_get_contents($temporaryPath));

            return $path;
        } finally {
            @unlink($temporaryPath);
        }
    }


    public function faqs(Trip $trip, Request $request): RedirectResponse
    {
        $request->validate([
            'faqs' => ['nullable', 'array'],
            'faqs.*.question' => ['required_with:faqs', 'string', 'max:1000'],
            'faqs.*.answer' => ['required_with:faqs', 'string', 'max:5000'],
        ]);

        $trip->faqs()->delete();

        foreach ($request->faqs ?? [] as $index => $faqData) {
            $trip->faqs()->create([
                'question' => $faqData['question'],
                'answer' => $faqData['answer'],
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }

        return $this->stepRedirect($trip, $request)->with('status', 'FAQs saved successfully.');
    }



    public function addons(Trip $trip, Request $request): RedirectResponse { if ($trip->pricing_mode === 'enquiry') return $this->stepRedirect($trip, $request)->withErrors(['addons' => 'Add-ons can be offered only for fixed or route-based pricing.']); $this->trips->replaceAddons($trip, $this->rows($request, 'addons', ['name' => ['required','string','max:160'], 'description' => ['nullable','string','max:1000'], 'price_minor' => ['required','integer','min:0'], 'max_quantity' => ['required','integer','min:1','max:20'], 'is_active' => ['nullable','boolean']])); return $this->stepRedirect($trip, $request)->with('status', 'Add-ons saved.'); }
    public function prices(Trip $trip, Request $request): RedirectResponse { try { $this->trips->replacePrices($trip, $this->rows($request, 'prices', ['vehicle_id' => ['required','integer','exists:vehicles,id'], 'price_minor' => ['required','integer','min:0'], 'is_active' => ['nullable','boolean']])); } catch (RuntimeException $e) { return $this->stepRedirect($trip, $request)->withErrors(['prices' => $e->getMessage()]); } return $this->stepRedirect($trip, $request)->with('status', 'Vehicle prices saved.'); }
    public function pricing(Trip $trip, Request $request): RedirectResponse
    {
        try {
            $data = $request->validate([
                'pricing_mode' => ['required', 'in:fixed,distance,enquiry'],
                'prices' => ['nullable', 'array'],
                'prices.*.pickup_location_id' => ['nullable', 'integer', 'exists:locations,id'],
                'prices.*.vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
                'prices.*.price_minor' => ['nullable', 'integer', 'min:0'],
                'prices.*.is_active' => ['nullable', 'boolean'],
                'configure_addons' => ['nullable', 'boolean'],
                'addons' => ['nullable', 'array'],
                'addons.*.name' => ['required_with:addons', 'string', 'max:160'],
                'addons.*.description' => ['nullable', 'string', 'max:1000'],
                'addons.*.price_minor' => ['required_with:addons', 'integer', 'min:0'],
                'addons.*.max_quantity' => ['required_with:addons', 'integer', 'min:1', 'max:20'],
                'addons.*.is_active' => ['nullable', 'boolean'],
            ]);

            if ($data['pricing_mode'] === 'fixed') {
                if (! $trip->pickup_locations_only) throw new RuntimeException('Fixed trip prices are available only when selected pickup locations are enabled.');
                $pickupIds = $trip->tripLocations()->where('role', 'pickup')->pluck('location_id')->map(fn ($id) => (int) $id);
                if ($pickupIds->isEmpty()) throw new RuntimeException('Add at least one approved pickup location before configuring fixed prices.');
                $rows = collect($data['prices'] ?? [])->filter(fn (array $row) => (bool) ($row['is_active'] ?? false))->map(function (array $row) use ($pickupIds): array {
                    if (empty($row['pickup_location_id']) || empty($row['vehicle_id']) || ! array_key_exists('price_minor', $row) || $row['price_minor'] === null || $row['price_minor'] === '') throw new RuntimeException('Enter a fixed price for every vehicle you offer.');
                    if (! $pickupIds->contains((int) $row['pickup_location_id'])) throw new RuntimeException('Prices can only be configured for this trip’s approved pickup locations.');
                    return ['pickup_location_id' => (int) $row['pickup_location_id'], 'vehicle_id' => (int) $row['vehicle_id'], 'price_minor' => (int) $row['price_minor'], 'is_active' => true];
                })->values()->all();
                $this->trips->replacePrices($trip, $rows);
            }

            if ($data['pricing_mode'] === 'distance' && ! $trip->tripLocations()->where('role', 'main_destination')->exists()) {
                throw new RuntimeException('Choose a primary trip destination before enabling route-based pricing.');
            }

            if (($data['configure_addons'] ?? false) && in_array($data['pricing_mode'], ['fixed', 'distance'], true)) {
                $addonRows = collect($data['addons'] ?? [])->filter(fn (array $row) => collect($row)->except('is_active')->contains(fn ($value) => $value !== null && $value !== ''))->values()->all();
                $this->trips->replaceAddons($trip, $addonRows);
            }

            $trip->update(['pricing_mode' => $data['pricing_mode']]);
        } catch (RuntimeException $e) {
            return $this->stepRedirect($trip, $request)->withErrors(['pricing' => $e->getMessage()]);
        }

        return $this->stepRedirect($trip, $request)->with('status', 'Pricing configuration saved.');
    }

    public function pricingEstimates(Trip $trip): JsonResponse
    {
        $trip->loadMissing('tripLocations.location');
        $destination = $trip->tripLocations->firstWhere('role', 'main_destination')?->location;
        $pickups = $trip->tripLocations->where('role', 'pickup')->map(fn ($link) => $link->location)->filter(fn ($location) => $location?->is_active && $location->google_place_id);
        if (! $trip->pickup_locations_only || $pickups->isEmpty() || $destination === null || ! $destination->google_place_id) {
            return response()->json(['message' => 'Enable approved pickup locations and choose a primary destination before requesting route estimates.'], 422);
        }

        try {
            $vehicles = Vehicle::query()->where('is_active', true)->whereHas('category', fn ($query) => $query->where('is_active', true))->orderBy('name')->get();
            $estimates = $pickups->map(function (Location $pickup) use ($destination, $vehicles): array {
                $route = $this->maps->computeRoute($pickup->google_place_id, $destination->google_place_id);
                return [
                    'pickup_location_id' => $pickup->id,
                    'distance_km' => round(($route->distanceM * 2) / 1000, 1),
                    'duration_minutes' => (int) ceil(($route->durationS * 2) / 60),
                    'vehicle_prices' => $vehicles->mapWithKeys(fn (Vehicle $vehicle) => [$vehicle->id => $this->pricing->price($vehicle, $route, true, 0)->subtotal])->all(),
                ];
            })->values();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Live route estimates could not be loaded. Check the Google server key and the selected locations.'], 422);
        }

        return response()->json(['data' => $estimates]);
    }
    public function blackouts(Trip $trip, Request $request): RedirectResponse { $this->trips->replaceBlackouts($trip, $this->rows($request, 'blackouts', ['date' => ['required','date'], 'reason' => ['nullable','string','max:255']])); return $this->stepRedirect($trip, $request)->with('status', 'Blackout dates saved.'); }
    public function pickupDurations(Trip $trip, Request $request): RedirectResponse { try { $data = $request->validate(['pickup_durations' => ['required','array'], 'pickup_durations.*' => ['required','integer','min:1','max:1440']]); $this->trips->replacePickupDurations($trip, collect($data['pickup_durations'])->mapWithKeys(fn ($duration, $id) => [(int) $id => (int) $duration])->all()); } catch (RuntimeException $e) { return $this->stepRedirect($trip, $request)->withErrors(['durations' => $e->getMessage()]); } return $this->stepRedirect($trip, $request)->with('status', 'Pickup durations saved.'); }
    public function locations(Trip $trip, Request $request): RedirectResponse { try { $data = $request->validate(['pickup_locations_only' => ['nullable','boolean'], 'pickup_location_ids' => ['nullable','array'], 'pickup_location_ids.*' => ['integer','distinct','exists:locations,id'], 'main_destination_location_id' => ['nullable','integer','exists:locations,id'], 'stop_location_ids' => ['nullable','array'], 'stop_location_ids.*' => ['integer','distinct','exists:locations,id']]); $trip->update(['pickup_locations_only' => (bool) ($data['pickup_locations_only'] ?? false), 'meeting_point_required' => false]); $this->trips->replaceLocations($trip, $data); } catch (RuntimeException $e) { return $this->stepRedirect($trip, $request)->withErrors(['locations' => $e->getMessage()]); } return $this->stepRedirect($trip, $request)->with('status', 'Trip locations and pickup rule saved.'); }
    public function status(Trip $trip, Request $request): RedirectResponse { $action = $request->validate(['action' => ['required','in:publish,unpublish,archive']])['action']; try { if ($action === 'publish') $this->trips->publish($trip); elseif ($action === 'archive') { if (Booking::query()->where('trip_id', $trip->id)->whereIn('status', ['confirmed','assigned','in_progress'])->whereHas('legs', fn ($q) => $q->where('starts_at', '>', now()))->exists()) throw new RuntimeException('This trip has future confirmed bookings and cannot be archived.'); $trip->update(['status' => 'archived']); } else $trip->update(['status' => 'draft']); } catch (RuntimeException $e) { return $this->stepRedirect($trip, $request)->withErrors(['status' => $e->getMessage()]); } return $this->stepRedirect($trip, $request)->with('status', 'Trip status updated.'); }
    public function cover(Trip $trip, Request $request): RedirectResponse|JsonResponse { $request->validate(['cover' => ['required','image','mimes:jpg,jpeg,png,webp','max:10240']]); $trip->addMediaFromRequest('cover')->toMediaCollection('cover'); return $this->mediaResponse($trip, $request, 'Trip cover saved.'); }
    public function gallery(Trip $trip, Request $request): RedirectResponse|JsonResponse { $request->validate(['images' => ['required','array','min:1','max:20'], 'images.*' => ['required','image','mimes:jpg,jpeg,png,webp','max:10240']]); foreach ($request->file('images', []) as $image) $trip->addMedia($image)->toMediaCollection('gallery'); return $this->mediaResponse($trip, $request, 'Trip gallery images uploaded.'); }
    public function reorderGallery(Trip $trip, Request $request): RedirectResponse|JsonResponse { $data = $request->validate(['media_ids' => ['required','array'], 'media_ids.*' => ['required','integer','distinct']]); $ids = array_map('intval', $data['media_ids']); $ownedIds = $trip->getMedia('gallery')->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(); $sortedIds = collect($ids)->sort()->values()->all(); if (count($ids) !== count($ownedIds) || $sortedIds !== $ownedIds) throw ValidationException::withMessages(['media_ids' => 'The gallery order must include every gallery image exactly once.']); Media::setNewOrder($ids); return $this->mediaResponse($trip, $request, 'Gallery order saved.'); }
    public function destroyGalleryImage(Trip $trip, int $mediaId, Request $request): RedirectResponse|JsonResponse { $media = $trip->getMedia('gallery')->firstWhere('id', $mediaId); abort_if($media === null, 404); $media->delete(); return $this->mediaResponse($trip, $request, 'Gallery image removed.'); }
    private function overview(Request $request): array { $data = $request->validate(['title' => ['required','string','max:180'], 'slug' => ['nullable','string','max:180'], 'subtitle' => ['nullable','string','max:255'], 'summary' => ['required','string','max:300'], 'description' => ['nullable','string','max:5000'], 'region' => ['required','string','max:100'], 'traveller_limits_enabled' => ['nullable','boolean'], 'min_travellers' => ['nullable','integer','min:1','max:99','required_if:traveller_limits_enabled,1'], 'max_travellers' => ['nullable','integer','min:1','max:99','gte:min_travellers','required_if:traveller_limits_enabled,1'], 'is_featured' => ['nullable','boolean'], 'meta_title' => ['nullable','string','max:255'], 'meta_description' => ['nullable','string','max:160']]); $data['traveller_limits_enabled'] = (bool) ($data['traveller_limits_enabled'] ?? false); $data['meeting_point_required'] = false; return $data; }

    private function sanitizeDescription(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') return null;

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="trip-content">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('trip-content');
        if ($root === null) return null;

        $allowed = ['a', 'b', 'blockquote', 'br', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'hr', 'i', 'li', 'ol', 'p', 'strong', 'u', 'ul'];
        foreach (iterator_to_array($root->getElementsByTagName('*')) as $element) {
            if (! in_array($element->tagName, $allowed, true)) {
                $parent = $element->parentNode;
                while ($element->firstChild) $parent?->insertBefore($element->firstChild, $element);
                $parent?->removeChild($element);
                continue;
            }

            $href = $element->tagName === 'a' ? trim($element->getAttribute('href')) : null;
            foreach (iterator_to_array($element->attributes) as $attribute) $element->removeAttribute($attribute->name);
            if ($href !== null && preg_match('~^(https?://|mailto:|tel:|/)~i', $href)) $element->setAttribute('href', $href);
        }

        $content = '';
        foreach ($root->childNodes as $node) $content .= $document->saveHTML($node);

        return trim($content) ?: null;
    }
    /** @return list<array<string,mixed>> */
    private function rows(Request $request, string $key, array $rules): array { $data = $request->validate([$key => ['nullable','array'], $key.'.*' => ['array'], ...collect($rules)->mapWithKeys(fn ($rule, $field) => [$key.'.*.'.$field => $rule])->all()]); return array_values(array_filter($data[$key] ?? [], fn ($row) => is_array($row) && collect($row)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty())); }

    private function stepRedirect(Trip $trip, Request $request): RedirectResponse
    {
        $allowed = ['overview', 'locations', 'content', 'departures', 'pricing', 'publish'];
        $step = $request->input('editor_step');
        return redirect()->to(route('admin.trips.edit', $trip).'#'.(in_array($step, $allowed, true) ? $step : 'overview'));
    }

    private function mediaResponse(Trip $trip, Request $request, string $message): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) return $this->stepRedirect($trip, $request)->with('status', $message);

        $trip = $trip->fresh('media');
        $cover = $trip->getFirstMedia('cover');

        return response()->json(['message' => $message, 'data' => [
            'cover' => $cover ? $this->mediaItem($cover) : null,
            'gallery' => $trip->getMedia('gallery')->sortBy('order_column')->values()->map(fn (Media $media) => $this->mediaItem($media))->all(),
        ]]);
    }

    /** @return array{id:int,url:string,name:string,order:int} */
    private function mediaItem(Media $media): array
    {
        return ['id' => $media->id, 'url' => $media->getUrl(), 'name' => $media->name, 'order' => $media->order_column];
    }
}
