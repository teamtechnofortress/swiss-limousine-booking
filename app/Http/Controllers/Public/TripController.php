<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\TripEnquiry;
use App\Services\Availability\AvailabilityService;
use App\Services\Settings\SettingsService;
use App\Services\Trips\TripSlotService;
use App\Services\Trips\TripQuoteService;
use App\Http\Requests\Public\StoreTripQuoteRequest;
use App\Http\Resources\QuoteResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TripController extends Controller
{
    public function index(Request $request, SettingsService $settings): JsonResponse
    {
        abort_unless($settings->bool('trips_enabled'), 404);
        $featured = $request->boolean('featured');
        $trips = Trip::query()->published()
            ->when($featured, fn ($q) => $q->where('is_featured', true))
            ->with([
                'media',
                'prices' => fn ($prices) => $prices->where('is_active', true)
                    ->whereHas('vehicle', fn ($vehicles) => $vehicles->where('is_active', true)->whereHas('category', fn ($categories) => $categories->where('is_active', true)))
                    ->whereHas('pickupLocation', fn ($locations) => $locations->where('is_active', true)->whereNull('locations.deleted_at')),
            ])
            ->orderBy('sort_order')->orderBy('title')->get();
        return response()->json(['data' => $trips->map(fn (Trip $trip) => $this->card($trip))->values()]);
    }

    public function show(string $slug, SettingsService $settings): JsonResponse
    {
        abort_unless($settings->bool('trips_enabled'), 404);
        $trip = Trip::query()->published()->where('slug', $slug)->with('media')->firstOrFail();

        return response()->json(['data' => [
            'slug' => $trip->slug,
            'title' => $trip->title,
            'subtitle' => $trip->subtitle,
            'summary' => $trip->summary,
            'description_html' => $trip->description,
            'region' => $trip->region,
            'cover_url' => $trip->getFirstMediaUrl('cover') ?: null,
            'gallery' => $trip->getMedia('gallery')->map(fn ($media) => ['id' => $media->id, 'url' => $media->getUrl()])->values(),
        ]]);
    }

    public function availability(string $slug, Request $request, SettingsService $settings, TripSlotService $slots, AvailabilityService $availability): JsonResponse
    {
        abort_unless($settings->bool('trips_enabled'), 404);
        $data = $request->validate(['from' => ['required','date'], 'to' => ['required','date','after_or_equal:from','before_or_equal:'.CarbonImmutable::parse($request->input('from'))->addDays(61)->toDateString()], 'travellers' => ['nullable','integer','min:1','max:99']]);
        $trip = Trip::query()->published()->where('slug', $slug)->with(['departures' => fn ($q) => $q->where('is_active', true), 'blackouts', 'prices.vehicle'])->firstOrFail();
        $travellers = (int) ($data['travellers'] ?? 1);
        $from = CarbonImmutable::parse($data['from'], 'Europe/Zurich')->startOfDay(); $to = CarbonImmutable::parse($data['to'], 'Europe/Zurich')->startOfDay();
        $entries = [];
        for ($date = $from; $date->lte($to); $date = $date->addDay()) foreach ($trip->departures as $departure) {
            $reason = $this->status($trip, $departure, $date, $travellers, $settings, $slots, $availability);
            $entries[] = ['date' => $date->toDateString(), 'departure_id' => $departure->id, 'start_time' => substr((string) $departure->start_time, 0, 5), ...$reason];
        }
        return response()->json(['data' => $entries]);
    }

    public function enquiry(string $slug, Request $request, SettingsService $settings): JsonResponse
    {
        abort_unless($settings->bool('trips_enabled'), 404);
        $trip = Trip::query()->published()->where('slug', $slug)->firstOrFail();
        $data = $request->validate(['name' => ['required','string','max:120'], 'email' => ['required','email','max:190'], 'phone' => ['required','string','max:30'], 'travel_date' => ['nullable','date'], 'travellers' => ['nullable','integer','min:1','max:99'], 'message' => ['required','string','max:3000']]);
        TripEnquiry::query()->create([...$data, 'trip_id' => $trip->id]);
        return response()->json(['data' => ['message' => 'Your trip enquiry has been received.']], 201);
    }

    public function quote(string $slug, StoreTripQuoteRequest $request, SettingsService $settings, TripQuoteService $quotes): JsonResponse
    {
        abort_unless($settings->bool('trips_enabled'), 404);
        $trip = Trip::query()->published()->where('slug', $slug)->firstOrFail();
        $snapshot = $quotes->create($trip, $request->integer('departure_id') ?: null, $request->input('custom_departure_time'), $request->travelDate(), (int) $request->input('travellers'), (string) $request->input('pickup_place_id'), trim((string) $request->input('pickup_address')), $request->addons());
        return (new QuoteResource($snapshot))->additional(['meta' => ['type' => 'trip', 'trip_slug' => $trip->slug]])->response()->setStatusCode(201);
    }

    /** @return array{status:string,free_vehicles?:int} */
    private function status(Trip $trip, $departure, CarbonImmutable $date, int $travellers, SettingsService $settings, TripSlotService $slots, AvailabilityService $availability): array
    {
        if ($trip->blackouts->contains(fn ($b) => $b->date->isSameDay($date))) return ['status' => 'blackout'];
        if ($trip->pricing_mode === 'enquiry') return ['status' => 'enquiry'];
        $window = $slots->window($trip, $departure, $date);
        if ($window['start']->lt(CarbonImmutable::now('UTC')->addHours($settings->int('trip_min_notice_hours')))) return ['status' => 'too_soon'];
        $vehicles = $trip->pricing_mode === 'distance'
            ? Vehicle::query()->with('category')->bookable()->where('passenger_capacity', '>=', $travellers)->get()
            : $trip->prices->filter(fn ($price) => $price->is_active && $price->vehicle?->is_active && $price->vehicle->passenger_capacity >= $travellers)->pluck('vehicle')->filter();
        if ($vehicles->isEmpty()) return ['status' => 'no_price'];
        $free = $vehicles->filter(fn ($vehicle) => $availability->hasFreeUnit($vehicle, $window['from'], $window['to']))->count();
        return $free ? ['status' => 'available', 'free_vehicles' => $free] : ['status' => 'sold_out'];
    }

    /** @return array<string,mixed> */
    private function card(Trip $trip): array
    {
        return ['slug' => $trip->slug, 'title' => $trip->title, 'subtitle' => $trip->subtitle, 'summary' => $trip->summary, 'description_html' => $trip->description, 'region' => $trip->region, 'pricing_mode' => $trip->pricing_mode, 'starting_from_price_minor' => $trip->pricing_mode === 'fixed' ? $trip->prices->min('price_minor') : null, 'cover_url' => $trip->getFirstMediaUrl('cover') ?: null];
    }
}
