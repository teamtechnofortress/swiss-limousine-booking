<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\Availability\AvailabilityService;
use App\Services\Settings\SettingsService;
use App\Services\Trips\TripSlotService;
use App\Services\Trips\TripQuoteService;
use App\Services\Trips\TripContentData;
use App\Services\Trips\TripBookingConfiguration;
use App\Http\Requests\Public\StoreTripQuoteRequest;
use App\Http\Resources\QuoteResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class TripController extends Controller
{
    public function __construct(private readonly TripBookingConfiguration $bookingConfiguration) {}

    public function index(Request $request, SettingsService $settings): JsonResponse
    {
        abort_unless($settings->bool('trips_enabled'), 404);

        return response()->json(['data' => $this->listingCards($request->boolean('featured'))]);
    }

    /** A dedicated, cache-friendly endpoint for homepage and featured-trip sections. */
    public function featured(SettingsService $settings): JsonResponse
    {
        abort_unless($settings->bool('trips_enabled'), 404);

        return response()->json(['data' => $this->listingCards(featured: true)]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function listingCards(bool $featured): Collection
    {
        return Trip::query()->published()
            ->when($featured, fn ($q) => $q->where('is_featured', true))
            ->with([
                'media',
                'prices' => fn ($prices) => $prices->where('is_active', true)
                    ->whereHas('vehicle', fn ($vehicles) => $vehicles->where('is_active', true)->whereHas('category', fn ($categories) => $categories->where('is_active', true)))
                    ->whereHas('pickupLocation', fn ($locations) => $locations->where('is_active', true)->whereNull('locations.deleted_at')),
            ])
            ->orderBy('sort_order')->orderBy('title')->get()
            ->map(fn (Trip $trip) => $this->card($trip))
            ->values();
    }

    public function show(string $slug, SettingsService $settings): JsonResponse
    {
        abort_unless($settings->bool('trips_enabled'), 404);
        $trip = Trip::query()->published()->where('slug', $slug)->with('media')->firstOrFail();
        $booking = $this->bookingConfiguration->forTrip($trip);
        $content = TripContentData::forTrip($trip);

        return response()->json(['data' => [
            'slug' => $trip->slug,
            'title' => $trip->title,
            'subtitle' => $trip->subtitle,
            'summary' => $trip->summary,
            'description_html' => $trip->description,
            'hero_image_url' => $content['hero_image_url'] ?? null,
            'faqs' => $trip->faqs,
            'region' => $trip->region,
            'cover_url' => $trip->getFirstMediaUrl('cover') ?: null,
            'gallery' => $trip->getMedia('gallery')->map(fn ($media) => ['id' => $media->id, 'url' => $media->getUrl()])->values(),
            // An enquiry is always available. This only controls whether the customer may
            // start the online quote-and-book flow; the quote endpoint makes the live checks.
            'pricing_mode' => $trip->pricing_mode,
            'booking' => [
                'start_booking_allowed' => $booking['start_booking_allowed'],
                'reason' => $booking['reason'],
                'pickup_locations_only' => $trip->pickup_locations_only,
                'departure_locations' => $booking['pickup']['locations'],
                'starting_from_price_minor' => $booking['pricing']['starting_from_price_minor'],
            ],
        ]]);
    }

    /** Complete static configuration for the trip booking flow. */
    public function journeyConfig(string $slug, SettingsService $settings): JsonResponse
    {
        abort_unless($settings->bool('trips_enabled'), 404);
        $trip = Trip::query()->published()->where('slug', $slug)->firstOrFail();
        $booking = $this->bookingConfiguration->forTrip($trip);

        return response()->json(['data' => [
            'start_booking_allowed' => $booking['start_booking_allowed'],
            'reason' => $booking['reason'],
            'pickup' => $booking['pickup'],
            'schedule' => $booking['schedule'],
            'travellers' => $booking['travellers'],
            'pricing' => $booking['pricing'],
            'quote_endpoint' => $booking['flow']['quote_endpoint'],
            'enquiry_endpoint' => $booking['flow']['enquiry_endpoint'],
        ]]);
    }

    public function availability(string $slug, Request $request, SettingsService $settings, TripSlotService $slots, AvailabilityService $availability): JsonResponse
    {
        abort_unless($settings->bool('trips_enabled'), 404);
        $data = $request->validate(['from' => ['required','date'], 'to' => ['required','date','after_or_equal:from','before_or_equal:'.CarbonImmutable::parse($request->input('from'))->addDays(61)->toDateString()], 'travellers' => ['nullable','integer','min:1','max:99'], 'pickup_location_id' => ['required','integer']]);
        $trip = Trip::query()->published()->where('slug', $slug)->with(['departures' => fn ($q) => $q->where('is_active', true), 'blackouts', 'prices.vehicle', 'tripLocations.location'])->firstOrFail();
        $pickupLink = $trip->pickup_locations_only
            ? $trip->tripLocations->where('role', \App\Models\TripLocation::PICKUP)->first(fn ($link) => $link->location_id === (int) $data['pickup_location_id'] && $link->location?->is_active && $link->duration_minutes > 0)
            : null;
        if ($pickupLink === null) {
            throw new \App\Exceptions\BookingException('trip_pickup_not_allowed', 'Please choose one of this trip\'s available pickup locations.');
        }
        $travellers = (int) ($data['travellers'] ?? 1);
        if ($trip->traveller_limits_enabled && ($travellers < $trip->min_travellers || $travellers > $trip->max_travellers)) {
            throw new \App\Exceptions\BookingException('trip_traveller_limit', "This trip is available for {$trip->min_travellers} to {$trip->max_travellers} travellers.", 422);
        }
        $from = CarbonImmutable::parse($data['from'], 'Europe/Zurich')->startOfDay(); $to = CarbonImmutable::parse($data['to'], 'Europe/Zurich')->startOfDay();
        $entries = [];
        for ($date = $from; $date->lte($to); $date = $date->addDay()) foreach ($trip->departures as $departure) {
            $reason = $this->status($trip, $departure, $date, $travellers, $pickupLink->location_id, (int) $pickupLink->duration_minutes, $settings, $slots, $availability);
            $entries[] = ['date' => $date->toDateString(), 'departure_id' => $departure->id, 'start_time' => substr((string) $departure->start_time, 0, 5), ...$reason];
        }
        return response()->json(['data' => $entries]);
    }

    public function quote(string $slug, StoreTripQuoteRequest $request, SettingsService $settings, TripQuoteService $quotes): JsonResponse
    {
        abort_unless($settings->bool('trips_enabled'), 404);
        $trip = Trip::query()->published()->where('slug', $slug)->firstOrFail();
        $snapshot = $quotes->create($trip, $request->integer('departure_id') ?: null, $request->input('custom_departure_time'), $request->travelDate(), (int) $request->input('travellers'), (string) $request->input('pickup_place_id'), trim((string) $request->input('pickup_address')));
        return (new QuoteResource($snapshot))->additional(['meta' => ['type' => 'trip', 'trip_slug' => $trip->slug]])->response()->setStatusCode(201);
    }

    /** @return array{status:string,free_vehicles?:int} */
    private function status(Trip $trip, $departure, CarbonImmutable $date, int $travellers, int $pickupLocationId, int $durationMinutes, SettingsService $settings, TripSlotService $slots, AvailabilityService $availability): array
    {
        if ($trip->blackouts->contains(fn ($b) => $b->date->isSameDay($date))) return ['status' => 'blackout'];
        if ($trip->pricing_mode === 'enquiry') return ['status' => 'enquiry'];
        $window = $slots->window($departure, $date, $durationMinutes);
        if ($window['start']->lt(CarbonImmutable::now('UTC')->addHours($settings->int('trip_min_notice_hours')))) return ['status' => 'too_soon'];
        $vehicles = $trip->pricing_mode === 'distance'
            ? Vehicle::query()->with('category')->bookable()->where('passenger_capacity', '>=', $travellers)->get()
            : $trip->prices->filter(fn ($price) => (int) $price->pickup_location_id === $pickupLocationId && $price->is_active && $price->vehicle?->is_active && $price->vehicle->passenger_capacity >= $travellers)->pluck('vehicle')->filter();
        if ($vehicles->isEmpty()) return ['status' => 'no_price'];
        $free = $vehicles->filter(fn ($vehicle) => $availability->hasFreeUnit($vehicle, $window['from'], $window['to']))->count();
        return $free ? ['status' => 'available', 'free_vehicles' => $free] : ['status' => 'sold_out'];
    }

    /** @return array<string,mixed> */
    private function card(Trip $trip): array
    {
        $cover = $trip->getFirstMedia('cover');
        $originalCoverUrl = $cover?->getUrl();

        return [
            'slug' => $trip->slug,
            'title' => $trip->title,
            'subtitle' => $trip->subtitle,
            'summary' => $trip->summary,
            'description_html' => $trip->description,
            'hero_image_url' => TripContentData::forTrip($trip)['hero_image_url'] ?? null,
            'region' => $trip->region,
            'pricing_mode' => $trip->pricing_mode,
            'starting_from_price_minor' => $trip->pricing_mode === 'fixed' ? $trip->prices->min('price_minor') : null,
            // Kept for existing clients; this is the original, full-resolution upload.
            'cover_url' => $originalCoverUrl,
            'thumbnail_url' => $cover?->hasGeneratedConversion('thumbnail') ? $cover->getUrl('thumbnail') : $originalCoverUrl,
            'original_cover_url' => $originalCoverUrl,
        ];
    }
}
