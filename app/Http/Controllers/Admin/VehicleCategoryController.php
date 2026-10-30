<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\VehicleCategoryResource;
use App\Models\VehicleCategory;
use App\Services\Vehicles\VehicleCategoryManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class VehicleCategoryController extends Controller
{
    public function __construct(private readonly VehicleCategoryManager $categories) {}

    public function index(): AnonymousResourceCollection
    {
        return VehicleCategoryResource::collection(VehicleCategory::query()->withCount('vehicles')->with('media')->orderBy('sort_order')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $category = $this->categories->create($this->validated($request), $request->user());
        $this->storeLogo($category, $request);

        return (new VehicleCategoryResource($category->fresh()->load('media')->loadCount('vehicles')))->response()->setStatusCode(201);
    }

    public function update(VehicleCategory $category, Request $request): VehicleCategoryResource
    {
        $category = $this->categories->update($category, $this->validated($request, $category), $request->user());
        $this->storeLogo($category, $request);

        return new VehicleCategoryResource($category->fresh()->load('media')->loadCount('vehicles'));
    }

    public function destroy(VehicleCategory $category, Request $request): JsonResponse
    {
        $this->categories->delete($category, $request->user());

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?VehicleCategory $category = null): array
    {
        $slug = Rule::unique('vehicle_categories', 'slug');
        $name = Rule::unique('vehicle_categories', 'name');
        if ($category !== null) {
            $slug->ignore($category);
            $name->ignore($category);
        }

        return $request->validate([
            'name' => [$category === null ? 'required' : 'sometimes', 'string', 'max:80', $name],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'between:0,65535'],
            'is_active' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
        ]);
    }

    private function storeLogo(VehicleCategory $category, Request $request): void
    {
        if ($request->hasFile('logo')) {
            $category->addMediaFromRequest('logo')->toMediaCollection('logo');
        }
    }
}
