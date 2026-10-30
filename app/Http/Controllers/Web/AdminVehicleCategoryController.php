<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\VehicleCategory;
use App\Services\Vehicles\VehicleCategoryManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminVehicleCategoryController extends Controller
{
    public function __construct(private readonly VehicleCategoryManager $categories) {}

    public function index(): View
    {
        return view('admin.vehicle-categories.index', [
            'categories' => VehicleCategory::query()->with(['media'])->withCount('vehicles')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = $this->categories->create($this->validated($request), $request->user());
        $this->storeLogo($category, $request);

        return back()->with('status', 'Vehicle category created.');
    }

    public function update(VehicleCategory $category, Request $request): RedirectResponse
    {
        $category = $this->categories->update($category, $this->validated($request, $category), $request->user());
        $this->storeLogo($category, $request);

        return back()->with('status', 'Vehicle category updated.');
    }

    public function destroy(VehicleCategory $category, Request $request): RedirectResponse
    {
        $this->categories->delete($category, $request->user());

        return back()->with('status', 'Vehicle category deleted.');
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
            'name' => ['required', 'string', 'max:80', $name],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'between:0,65535'],
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
