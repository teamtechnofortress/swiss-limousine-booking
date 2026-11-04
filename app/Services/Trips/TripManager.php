<?php

namespace App\Services\Trips;

use App\Models\Trip;
use App\Models\TripPrice;
use App\Models\TripLocation;
use App\Models\Location;
use App\Models\Vehicle;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class TripManager
{
    public function __construct(private readonly SettingsService $settings) {}
    public function create(array $data): Trip
    {
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['title']);
        $data['pricing_mode'] ??= 'fixed';
        $data['min_travellers'] ??= 1;
        $data['max_travellers'] ??= 99;
        $data['traveller_limits_enabled'] ??= true;
        return Trip::query()->create($data);
    }

    public function update(Trip $trip, array $data): Trip
    {
        $trip->fill([...$data, 'slug' => isset($data['slug']) ? $this->uniqueSlug($data['slug'], $trip->id) : $trip->slug])->save();
        return $trip;
    }

    /** @param list<array<string, mixed>> $rows */
    public function replaceDepartures(Trip $trip, array $rows): void
    {
        DB::transaction(function () use ($trip, $rows): void {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            $trip->departures()->delete();
            foreach ($rows as $index => $row) $trip->departures()->create(['start_time' => $row['start_time'], 'is_active' => (bool) ($row['is_active'] ?? true), 'sort_order' => $index]);
        });
    }

    /** @param list<array<string, mixed>> $rows */
    public function replaceItems(Trip $trip, array $rows): void
    {
        DB::transaction(function () use ($trip, $rows): void {
            $trip->items()->delete();
            foreach ($rows as $index => $row) $trip->items()->create([...$row, 'sort_order' => $index]);
        });
    }

    /** @param list<array<string, mixed>> $rows */
    public function replaceAddons(Trip $trip, array $rows): void
    {
        DB::transaction(function () use ($trip, $rows): void {
            $trip->addons()->delete();
            foreach ($rows as $index => $row) $trip->addons()->create([...$row, 'sort_order' => $index, 'is_active' => (bool) ($row['is_active'] ?? true)]);
        });
    }

    /** @param list<array<string, mixed>> $rows */
    public function replacePrices(Trip $trip, array $rows): void
    {
        DB::transaction(function () use ($trip, $rows): void {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            $normalised = collect($rows)->map(fn (array $row) => [...$row, 'is_active' => (bool) ($row['is_active'] ?? true)]);
            if ($normalised->map(fn (array $row) => ($row['pickup_location_id'] ?? '').':'.$row['vehicle_id'])->duplicates()->isNotEmpty()) {
                throw new RuntimeException('Each pickup location can have one price per vehicle.');
            }
            $trip->prices()->delete();
            foreach ($normalised as $row) $trip->prices()->create($row);
        });
    }

    /** @param list<array<string, mixed>> $rows */
    public function replaceBlackouts(Trip $trip, array $rows): void
    {
        DB::transaction(function () use ($trip, $rows): void {
            $trip->blackouts()->delete();
            foreach ($rows as $row) $trip->blackouts()->create($row);
        });
    }

    /** @param array{pickup_location_ids?:list<int>,main_destination_location_id?:int|null} $data */
    public function replaceLocations(Trip $trip, array $data): void
    {
        $rows = [];
        $pickupIds = array_values(array_unique(array_map('intval', $data['pickup_location_ids'] ?? [])));
        $existingDurations = $trip->tripLocations()
            ->where('role', TripLocation::PICKUP)
            ->pluck('duration_minutes', 'location_id');
        foreach ($pickupIds as $id) $rows[] = ['location_id' => $id, 'role' => TripLocation::PICKUP, 'duration_minutes' => $existingDurations[$id] ?? null];
        if (! empty($data['main_destination_location_id'])) $rows[] = ['location_id' => (int) $data['main_destination_location_id'], 'role' => TripLocation::MAIN_DESTINATION];
        $ids = collect($rows)->pluck('location_id')->unique();
        if ($ids->isNotEmpty() && Location::query()->active()->whereIn('id', $ids)->count() !== $ids->count()) throw new RuntimeException('Trips can only use active locations from the Locations catalogue.');
        if ($trip->pickup_locations_only && ! collect($rows)->contains('role', TripLocation::PICKUP)) throw new RuntimeException('Choose at least one approved pickup location before enabling the trip pickup restriction.');
        DB::transaction(function () use ($trip, $rows): void {
            $trip->tripLocations()->delete();
            foreach ($rows as $index => $row) $trip->tripLocations()->create([...$row, 'sort_order' => $index]);
        });
    }

    /** @param array<int, int> $durations */
    public function replacePickupDurations(Trip $trip, array $durations): void
    {
        if (! $trip->pickup_locations_only) {
            throw new RuntimeException('Enable selected pickup locations before configuring durations.');
        }

        $pickupLinks = $trip->tripLocations()->where('role', TripLocation::PICKUP)->get();
        if ($pickupLinks->isEmpty()) {
            throw new RuntimeException('Select at least one approved pickup location before configuring durations.');
        }

        foreach ($pickupLinks as $link) {
            if (($durations[$link->location_id] ?? 0) < 1) {
                throw new RuntimeException('Set a duration in minutes for every approved pickup location.');
            }
        }

        DB::transaction(function () use ($trip, $durations): void {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            foreach ($trip->tripLocations()->where('role', TripLocation::PICKUP)->lockForUpdate()->get() as $link) {
                $link->update(['duration_minutes' => (int) $durations[$link->location_id]]);
            }
        });
    }

    /** @return array<string, string> */
    public function checklist(Trip $trip): array
    {
        $today = now('Europe/Zurich')->toDateString();
        $vehicles = Vehicle::query()->where('is_active', true)->pluck('id');
        return [
            'overview' => trim($trip->title) !== '' && trim($trip->summary) !== '' && trim($trip->region) !== '' ? 'ready' : 'Add title, summary and region.',
            'cover' => $trip->hasMedia('cover') ? 'ready' : 'Add a cover image.',
            'content' => trim(strip_tags((string) $trip->description)) !== '' ? 'ready' : 'Add trip description content.',
            'departures' => ! $trip->fixed_departures_only || $trip->departures()->where('is_active', true)->exists() ? 'ready' : 'Add an active fixed departure time.',
            'pricing' => $this->pricingReady($trip, $vehicles) ? 'ready' : $this->pricingRequirement($trip),
            'trip pickups' => ! $trip->pickup_locations_only || $this->pickupLocationsReady($trip) ? 'ready' : 'Choose at least one active pickup location'.($this->settings->bool('restrict_pickups_enabled') ? ' that is also allowed globally.' : '.').'',
        ];
    }

    public function publish(Trip $trip): void
    {
        $failed = array_filter($this->checklist($trip), fn (string $status) => $status !== 'ready');
        if ($failed !== []) throw new RuntimeException('This trip cannot be published yet: '.implode(' ', $failed));
        $trip->forceFill(['status' => 'published', 'published_at' => now()])->save();
    }

    private function uniqueSlug(string $value, ?int $exceptId = null): string
    {
        $base = Str::slug($value) ?: 'trip'; $slug = $base; $number = 2;
        while (Trip::withTrashed()->where('slug', $slug)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists()) $slug = $base.'-'.++$number;
        return $slug;
    }

    private function pickupLocationsReady(Trip $trip): bool
    {
        $pickups = $trip->tripLocations()->where('role', TripLocation::PICKUP);
        $allowedLocation = fn ($query) => $query->active()
            ->when($this->settings->bool('restrict_pickups_enabled'), fn ($locations) => $locations->where('allowed_pickup', true));

        return $pickups->exists()
            && ! (clone $pickups)->where(fn ($query) => $query->whereNull('duration_minutes')->orWhere('duration_minutes', '<=', 0))->exists()
            && ! (clone $pickups)->whereDoesntHave('location', $allowedLocation)->exists();
    }

    private function pricingReady(Trip $trip, $vehicleIds): bool
    {
        if ($trip->pricing_mode === 'enquiry') return true;
        if ($trip->pricing_mode === 'distance') {
            return (! $trip->pickup_locations_only || $this->pickupLocationsReady($trip))
                && $trip->tripLocations()->where('role', TripLocation::MAIN_DESTINATION)->exists();
        }

        if (! $trip->pickup_locations_only || ! $this->pickupLocationsReady($trip)) return false;
        $pickupIds = $trip->tripLocations()->where('role', TripLocation::PICKUP)->pluck('location_id');

        return $pickupIds->every(fn ($pickupId) => $trip->prices()
            ->where('pickup_location_id', $pickupId)
            ->where('is_active', true)
            ->whereIn('vehicle_id', $vehicleIds)
            ->exists());
    }

    private function pricingRequirement(Trip $trip): string
    {
        if ($trip->pricing_mode === 'distance') {
            return $trip->pickup_locations_only
                ? 'Set a duration for every approved pickup location and choose a primary destination for route-based pricing.'
                : 'Choose a primary destination for route-based pricing.';
        }
        return ! $trip->pickup_locations_only
            ? 'Enable selected pickup locations before using fixed trip prices.'
            : 'Set a duration and add at least one active vehicle price for every pickup location.';
    }
}
