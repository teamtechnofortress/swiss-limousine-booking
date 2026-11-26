<?php

namespace App\Http\Controllers\Public;

use App\Data\RouteMetrics;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\Pricing\PricingService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VehiclePriceEstimateController extends Controller
{
    /**
     * Return distance-only estimates for every vehicle that is currently
     * configured for online booking. This does not reserve availability.
     */
    public function __invoke(Request $request, PricingService $pricing, SettingsService $settings): JsonResponse
    {
        $data = $request->validate([
            'distance_km' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:99'],
            'luggage' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $distanceM = (int) round((float) $data['distance_km'] * 1000);
        $currency = $settings->string('currency');
        $route = new RouteMetrics($distanceM, 0);
        $vehicles = Vehicle::query()
            ->with(['category', 'distancePriceRanges', 'media'])
            ->bookable()
            ->when(isset($data['passengers']), fn ($query) => $query->where('passenger_capacity', '>=', $data['passengers']))
            ->when(isset($data['luggage']), fn ($query) => $query->where('luggage_capacity', '>=', $data['luggage']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'distance_km' => $distanceM / 1000,
                'currency' => $currency,
                'passengers' => $data['passengers'] ?? null,
                'luggage' => $data['luggage'] ?? null,
                'vehicles' => $vehicles->map(function (Vehicle $vehicle) use ($pricing, $route, $currency): array {
                    $amount = $pricing->legAmount($vehicle, $route);

                    return [
                        'id' => $vehicle->id,
                        'name' => $vehicle->name,
                        'slug' => $vehicle->slug,
                        'thumbnail_url' => $vehicle->thumbnailUrl(),
                        'category' => [
                            'id' => $vehicle->category->id,
                            'name' => $vehicle->category->name,
                            'slug' => $vehicle->category->slug,
                        ],
                        'capacity' => [
                            'passengers' => $vehicle->passenger_capacity,
                            'luggage' => $vehicle->luggage_capacity,
                        ],
                        'amount_minor' => $amount,
                        'formatted_amount' => $currency.' '.number_format($amount / 100, 2),
                    ];
                })->values(),
            ],
        ]);
    }
}
