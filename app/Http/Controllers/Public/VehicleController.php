<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class VehicleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['category' => ['nullable', 'string', 'max:100']]);
        $vehicles = Vehicle::query()->where('is_active', true)->with(['category.media', 'media'])->withCount('fleetUnits')
            ->whereHas('category', fn ($query) => $query->active())
            ->when($data['category'] ?? null, fn ($query, string $category) => $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug', $category)))
            ->orderBy('sort_order')->orderBy('name')->get();

        return VehicleResource::collection($vehicles);
    }

    public function show(string $slug): VehicleResource
    {
        $vehicle = Vehicle::query()->where('is_active', true)->where('slug', $slug)->whereHas('category', fn ($query) => $query->active())->with(['category.media', 'media'])->withCount('fleetUnits')->firstOrFail();

        return new VehicleResource($vehicle);
    }
}
