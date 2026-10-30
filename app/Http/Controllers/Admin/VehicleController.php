<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Vehicles\StoreVehicleRequest;
use App\Http\Requests\Admin\Vehicles\UpdateVehicleRequest;
use App\Http\Resources\Admin\VehicleResource;
use App\Models\Vehicle;
use App\Services\Vehicles\VehicleManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class VehicleController extends Controller
{
    public function __construct(private readonly VehicleManager $vehicles) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:vehicle_categories,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ]);

        $vehicles = Vehicle::query()
            ->with(['category.media', 'fleetUnits', 'media'])
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $term = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($search) => $search->where('name', 'like', $term)->orWhere('model_name', 'like', $term)->orWhere('slug', 'like', $term));
            })
            ->when($filters['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 25));

        return VehicleResource::collection($vehicles);
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = $this->vehicles->create($request->validated(), $request->user());

        return (new VehicleResource($vehicle))->response()->setStatusCode(201);
    }

    public function show(Vehicle $vehicle): VehicleResource
    {
        return new VehicleResource($vehicle->load(['category.media', 'fleetUnits', 'media']));
    }

    public function update(Vehicle $vehicle, UpdateVehicleRequest $request): VehicleResource
    {
        return new VehicleResource($this->vehicles->update($vehicle, $request->validated(), $request->user()));
    }

    public function destroy(Vehicle $vehicle, Request $request): JsonResponse
    {
        $this->vehicles->delete($vehicle, $request->user());

        return response()->json(status: 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate(['vehicle_ids' => ['required', 'array', 'min:1'], 'vehicle_ids.*' => ['required', 'integer']]);
        $this->vehicles->reorder($data['vehicle_ids'], $request->user());

        return response()->json(['message' => 'Vehicle display order saved.']);
    }

    public function storeImages(Vehicle $vehicle, Request $request): VehicleResource
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:12'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        foreach ($request->file('images', []) as $image) {
            $vehicle->addMedia($image)->toMediaCollection('images');
        }

        activity('fleet')->causedBy($request->user())->performedOn($vehicle)->log('vehicle_images_added');

        return new VehicleResource($vehicle->fresh()->load(['category.media', 'fleetUnits', 'media']));
    }

    public function orderImages(Vehicle $vehicle, Request $request): VehicleResource
    {
        $data = $request->validate(['media_ids' => ['required', 'array'], 'media_ids.*' => ['required', 'integer', 'distinct']]);
        $ids = array_map('intval', $data['media_ids']);
        $ownedIds = $vehicle->getMedia('images')->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($ids);
        sort($ownedIds);
        if ($ids !== $ownedIds) {
            throw ValidationException::withMessages(['media_ids' => 'The image order must contain every image of this vehicle exactly once.']);
        }
        Media::setNewOrder($data['media_ids']);
        activity('fleet')->causedBy($request->user())->performedOn($vehicle)->log('vehicle_images_reordered');

        return new VehicleResource($vehicle->fresh()->load(['category.media', 'fleetUnits', 'media']));
    }

    public function destroyImage(Vehicle $vehicle, int $mediaId, Request $request): JsonResponse
    {
        $media = $vehicle->getMedia('images')->firstWhere('id', $mediaId);
        abort_if($media === null, 404);
        $media->delete();
        activity('fleet')->causedBy($request->user())->performedOn($vehicle)->log('vehicle_image_deleted');

        return response()->json(status: 204);
    }
}
