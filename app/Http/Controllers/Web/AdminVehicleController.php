<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Vehicles\StoreVehicleRequest;
use App\Http\Requests\Admin\Vehicles\UpdateVehicleRequest;
use App\Models\Booking;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\Settings\SettingsService;
use App\Services\Vehicles\VehicleManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class AdminVehicleController extends Controller
{
    public function __construct(
        private readonly VehicleManager $vehicles,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'category_id' => ['nullable', 'integer', 'exists:vehicle_categories,id'],
        ]);
        $vehicles = Vehicle::query()->with(['category.media', 'media'])
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $term = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($search) => $search->where('name', 'like', $term)->orWhere('model_name', 'like', $term)->orWhere('slug', 'like', $term));
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.vehicles.index', [
            'vehicles' => $vehicles,
            'filters' => $filters,
            'canReorder' => empty($filters['q']) && empty($filters['status']) && empty($filters['category_id']),
            'filterCategories' => VehicleCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.vehicles.create', $this->formData());
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $vehicle = $this->vehicles->create($request->validated(), $request->user());

        return redirect()->route('admin.vehicles.edit', $vehicle)->with('status', 'Vehicle draft created. Add images, then publish when ready.');
    }

    public function edit(Vehicle $vehicle): View
    {
        return view('admin.vehicles.edit', [
            ...$this->formData($vehicle),
            'vehicle' => $vehicle->load('media'),
            'hasBookings' => Booking::withTrashed()->where('vehicle_id', $vehicle->id)->exists(),
        ]);
    }

    public function update(Vehicle $vehicle, UpdateVehicleRequest $request): RedirectResponse
    {
        $this->vehicles->update($vehicle, $request->validated(), $request->user());

        return back()->with('status', 'Vehicle details saved.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate(['vehicle_ids' => ['required', 'array', 'min:1'], 'vehicle_ids.*' => ['required', 'integer']]);
        $this->vehicles->reorder($data['vehicle_ids'], $request->user());

        return back()->with('status', 'Vehicle display order saved.');
    }

    public function destroy(Vehicle $vehicle, Request $request): RedirectResponse
    {
        $this->vehicles->delete($vehicle, $request->user());

        return redirect()->route('admin.vehicles.index')->with('status', 'Vehicle deleted.');
    }

    public function uploadImages(Vehicle $vehicle, Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:12'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'make_primary' => ['nullable', 'boolean'],
        ]);
        $uploaded = [];
        foreach ($request->file('images', []) as $image) {
            $uploaded[] = $vehicle->addMedia($image)->toMediaCollection('images');
        }

        if ($data['make_primary'] ?? false) {
            $images = $vehicle->getMedia('images');
            $newImage = last($uploaded);
            if ($newImage !== null) {
                $orderedIds = [$newImage->id, ...$images->pluck('id')->reject(fn ($id) => (int) $id === $newImage->id)->all()];
                Media::setNewOrder($orderedIds);
            }
        }

        activity('fleet')->causedBy($request->user())->performedOn($vehicle)->log('vehicle_images_added');

        return $this->mediaResponse($vehicle, $request, 'Vehicle images uploaded.');
    }

    public function destroyImage(Vehicle $vehicle, int $mediaId, Request $request): RedirectResponse|JsonResponse
    {
        $media = $vehicle->getMedia('images')->firstWhere('id', $mediaId);
        abort_if($media === null, 404);
        $media->delete();
        activity('fleet')->causedBy($request->user())->performedOn($vehicle)->log('vehicle_image_deleted');

        return $this->mediaResponse($vehicle, $request, 'Vehicle image removed.');
    }

    public function makePrimaryImage(Vehicle $vehicle, int $mediaId, Request $request): RedirectResponse|JsonResponse
    {
        $images = $vehicle->getMedia('images');
        abort_unless($images->contains('id', $mediaId), 404);

        $orderedIds = [$mediaId, ...$images->pluck('id')->map(fn ($id) => (int) $id)->reject(fn (int $id) => $id === $mediaId)->all()];
        Media::setNewOrder($orderedIds);
        activity('fleet')->causedBy($request->user())->performedOn($vehicle)->log('vehicle_primary_image_changed');

        return $this->mediaResponse($vehicle, $request, 'Vehicle cover image updated.');
    }

    public function reorderImages(Vehicle $vehicle, Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['media_ids' => ['required', 'array'], 'media_ids.*' => ['required', 'integer', 'distinct']]);
        $ids = array_map('intval', $data['media_ids']);
        $ownedIds = $vehicle->getMedia('images')->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        if (count($ids) !== count($ownedIds) || collect($ids)->sort()->values()->all() !== $ownedIds) {
            throw ValidationException::withMessages(['media_ids' => 'The image order must contain every image of this vehicle exactly once.']);
        }

        Media::setNewOrder($ids);
        activity('fleet')->causedBy($request->user())->performedOn($vehicle)->log('vehicle_images_reordered');

        return $this->mediaResponse($vehicle, $request, 'Vehicle image order saved.');
    }

    private function mediaResponse(Vehicle $vehicle, Request $request, string $message): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('status', $message);
        }

        return response()->json(['message' => $message, 'data' => [
            'images' => $vehicle->fresh('media')->getMedia('images')->sortBy('order_column')->values()->map(fn (Media $media) => $this->mediaItem($media))->all(),
        ]]);
    }

    /** @return array{id:int,url:string,thumbnail_url:string,name:string,order:int} */
    private function mediaItem(Media $media): array
    {
        $url = $media->hasGeneratedConversion('web') ? $media->getUrl('web') : $media->getUrl();

        return [
            'id' => $media->id,
            'url' => $url,
            'thumbnail_url' => $media->hasGeneratedConversion('thumbnail') ? $media->getUrl('thumbnail') : $url,
            'name' => $media->name,
            'order' => $media->order_column,
        ];
    }

    /** @return array<string, mixed> */
    private function formData(?Vehicle $vehicle = null): array
    {
        return [
            'categories' => VehicleCategory::query()
                ->when(
                    $vehicle?->category_id,
                    fn ($query, int $categoryId) => $query->where(fn ($categories) => $categories->active()->orWhere('id', $categoryId)),
                    fn ($query) => $query->active(),
                )
                ->orderBy('sort_order')->orderBy('name')->get(),
            'amenities' => Vehicle::AMENITIES,
            'vatIncluded' => $this->settings->bool('vat_included'),
        ];
    }
}
