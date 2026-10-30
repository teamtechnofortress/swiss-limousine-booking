<?php

namespace App\Services\Vehicles;

use App\Models\User;
use App\Models\VehicleCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Category lifecycle rules shared by the HTML dashboard and JSON API. */
final class VehicleCategoryManager
{
    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor): VehicleCategory
    {
        $category = VehicleCategory::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $this->nextSlug($data['name']),
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $this->audit('vehicle_category_created', $category, $actor);

        return $category;
    }

    /** @param array<string, mixed> $data */
    public function update(VehicleCategory $category, array $data, ?User $actor): VehicleCategory
    {
        $category->update(Arr::except($data, ['logo']));
        $this->audit('vehicle_category_updated', $category, $actor);

        return $category->fresh();
    }

    public function delete(VehicleCategory $category, ?User $actor): void
    {
        if ($category->vehicles()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'This category is used by vehicles and cannot be deleted. Deactivate it instead.',
            ]);
        }

        DB::transaction(function () use ($category, $actor): void {
            $this->audit('vehicle_category_deleted', $category, $actor);
            $category->clearMediaCollection('logo');
            $category->delete();
        });
    }

    private function nextSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 90, '') ?: 'category';
        $slug = $base;
        $suffix = 2;
        while (VehicleCategory::query()->where('slug', $slug)->exists()) {
            $slug = Str::limit($base, 100 - strlen((string) $suffix) - 1, '').'-'.$suffix++;
        }

        return $slug;
    }

    private function audit(string $event, VehicleCategory $category, ?User $actor): void
    {
        $logger = activity('fleet')->performedOn($category);
        if ($actor !== null) {
            $logger->causedBy($actor);
        }
        $logger->log($event);
    }
}
