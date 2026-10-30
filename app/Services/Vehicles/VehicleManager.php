<?php

namespace App\Services\Vehicles;

use App\Models\Booking;
use App\Models\FleetUnit;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Applies vehicle rules in one place for both the dashboard and JSON API. */
final class VehicleManager
{
    public function __construct(private readonly SettingsService $settings) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor): Vehicle
    {
        return DB::transaction(function () use ($data, $actor): Vehicle {
            $slotsEnabled = array_key_exists('slot_check_enabled', $data)
                ? (bool) $data['slot_check_enabled']
                : $this->settings->bool('default_slot_check_enabled');

            $vehicle = Vehicle::query()->create([
                ...$this->attributes($data),
                'slug' => $data['slug'] ?? $this->nextSlug((string) $data['name']),
                'slot_check_enabled' => $slotsEnabled,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                // A new vehicle is deliberately a draft. Publishing is a deliberate next step.
                'is_active' => false,
            ]);

            $this->syncUnits($vehicle, $data['units'] ?? []);
            $this->audit('vehicle_created', $vehicle, $actor);

            return $vehicle->load(['category.media', 'fleetUnits', 'media']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Vehicle $vehicle, array $data, ?User $actor): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $data, $actor): Vehicle {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
            $turningSlotsOn = array_key_exists('slot_check_enabled', $data)
                && (bool) $data['slot_check_enabled']
                && ! $vehicle->slot_check_enabled;

            if (array_key_exists('units', $data)) {
                $this->syncUnits($vehicle, $data['units']);
            }

            $nextSlotsEnabled = array_key_exists('slot_check_enabled', $data)
                ? (bool) $data['slot_check_enabled']
                : $vehicle->slot_check_enabled;
            $nextActive = array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : $vehicle->is_active;
            $unitCount = $vehicle->fleetUnits()->count();

            if (($turningSlotsOn || ($nextActive && $nextSlotsEnabled)) && $unitCount === 0) {
                throw ValidationException::withMessages([
                    'units' => 'Add at least one physical vehicle before enabling availability checks or publishing this vehicle.',
                ]);
            }

            $attributes = $this->attributes($data);
            foreach (['slug', 'slot_check_enabled', 'sort_order', 'is_active'] as $key) {
                if (array_key_exists($key, $data)) {
                    $attributes[$key] = $data[$key];
                }
            }
            $vehicle->update($attributes);
            $this->audit('vehicle_updated', $vehicle, $actor);

            return $vehicle->load(['category.media', 'fleetUnits', 'media']);
        });
    }

    public function delete(Vehicle $vehicle, ?User $actor): void
    {
        if (Booking::withTrashed()->where('vehicle_id', $vehicle->id)->exists()) {
            throw ValidationException::withMessages([
                'vehicle' => 'This vehicle has booking history and cannot be deleted. Deactivate it instead.',
            ]);
        }

        DB::transaction(function () use ($vehicle, $actor): void {
            $vehicle->delete();
            $this->audit('vehicle_deleted', $vehicle, $actor);
        });
    }

    /** @param list<int|string> $orderedIds */
    public function reorder(array $orderedIds, ?User $actor): void
    {
        $ids = array_map('intval', $orderedIds);
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['vehicle_ids' => 'Each vehicle may appear only once.']);
        }

        $known = Vehicle::query()->whereIn('id', $ids)->count();
        if ($known !== count($ids)) {
            throw ValidationException::withMessages(['vehicle_ids' => 'One or more vehicles no longer exist.']);
        }

        DB::transaction(function () use ($ids, $actor): void {
            foreach ($ids as $index => $id) {
                Vehicle::query()->whereKey($id)->update(['sort_order' => $index + 1]);
            }
            activity('fleet')->causedBy($actor)->withProperties(['vehicle_ids' => $ids])->log('vehicles_reordered');
        });
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed> */
    private function attributes(array $data): array
    {
        $attributes = Arr::only($data, [
            'name', 'model_name', 'slug', 'category_id', 'description', 'passenger_capacity', 'luggage_capacity',
            'price_per_km', 'minimum_fare', 'slot_check_enabled', 'sort_order', 'is_active',
        ]);
        if (array_key_exists('amenities', $data)) {
            $attributes['amenities'] = array_values(array_unique($data['amenities'] ?? []));
        }

        return $attributes;
    }

    /** @param list<string> $plates */
    private function syncUnits(Vehicle $vehicle, array $plates): void
    {
        $normalised = collect($plates)
            ->map(fn (mixed $plate) => $this->normalisePlate((string) $plate))
            ->filter()
            ->values();

        if ($normalised->count() !== $normalised->unique()->count()) {
            throw ValidationException::withMessages(['units' => 'Each registration plate must be unique.']);
        }

        $duplicate = FleetUnit::query()
            ->whereIn('plate', $normalised->all())
            ->where('vehicle_id', '!=', $vehicle->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['units' => 'A registration plate is already assigned to another vehicle.']);
        }

        $existing = $vehicle->fleetUnits()->get()->keyBy('plate');
        $removed = $existing->keys()->diff($normalised);
        foreach ($removed as $plate) {
            /** @var FleetUnit $unit */
            $unit = $existing[$plate];
            $this->assertUnitCanBeRemoved($unit);
            $unit->delete();
        }
        foreach ($normalised->diff($existing->keys()) as $plate) {
            $vehicle->fleetUnits()->create(['plate' => $plate]);
        }
    }

    private function assertUnitCanBeRemoved(FleetUnit $unit): void
    {
        if ($unit->bookings()->withTrashed()->exists() || $unit->scheduleBlocks()->exists()) {
            throw ValidationException::withMessages([
                'units' => "{$unit->plate} is linked to a booking or availability hold and cannot be removed.",
            ]);
        }
    }

    private function nextSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 100, '');
        $base = $base !== '' ? $base : 'vehicle';
        $slug = $base;
        $suffix = 2;
        while (Vehicle::withTrashed()->where('slug', $slug)->exists()) {
            $slug = Str::limit($base, 100 - strlen((string) $suffix) - 1, '').'-'.$suffix++;
        }

        return $slug;
    }

    private function normalisePlate(string $plate): string
    {
        return Str::upper(preg_replace('/\s+/', '', $plate) ?? '');
    }

    private function audit(string $event, Vehicle $vehicle, ?User $actor): void
    {
        $logger = activity('fleet')->performedOn($vehicle);
        if ($actor !== null) {
            $logger->causedBy($actor);
        }
        $logger->log($event);
    }
}
