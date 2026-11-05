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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
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
        $data = $request->validate(['description' => ['nullable', 'string', 'max:50000']]);
        $trip->update(['description' => $this->sanitizeDescription($data['description'] ?? null)]);

        return $this->stepRedirect($trip, $request)->with('status', 'Trip content saved.');
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
