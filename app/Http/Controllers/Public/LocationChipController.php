<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LocationChipController extends Controller
{
    public function __invoke(Request $request, SettingsService $settings): JsonResponse
    {
        $role = $request->validate(['role' => ['nullable', 'in:pickup,dropoff']])['role'] ?? 'pickup';
        $restricted = $role === 'pickup' && $settings->bool('restrict_pickups_enabled');
        $locations = Location::query()->active()
            ->when($restricted, fn ($q) => $q->where('allowed_pickup', true), fn ($q) => $q->where('is_featured', true))
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'google_place_id', 'formatted_address', 'locality', 'country_code']);

        return response()->json([
            'restricted' => $restricted,
            'message' => $restricted ? $settings->string('restricted_pickup_message') : null,
            'data' => $locations,
        ]);
    }
}
