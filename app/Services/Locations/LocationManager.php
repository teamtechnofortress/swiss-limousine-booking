<?php

namespace App\Services\Locations;

use App\Models\Location;
use App\Models\Setting;
use App\Models\TripLocation;
use App\Services\Geo\GoogleMapsClient;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Keeps location catalogue flags valid even when edits arrive outside the web form. */
final class LocationManager
{
    public function __construct(
        private readonly GoogleMapsClient $maps,
        private readonly SettingsService $settings,
    ) {}

    public function createFromGoogle(string $placeId, array $input, ?int $actorId = null): Location
    {
        if (Location::withTrashed()->where('google_place_id', $placeId)->exists()) {
            throw new RuntimeException('This Google place is already in the locations catalogue.');
        }

        $details = $this->maps->catalogueLocationDetails($placeId);

        return DB::transaction(function () use ($details, $input, $actorId): Location {
            $name = trim((string) ($input['name'] ?? $details['name']));
            $location = Location::query()->create([
                ...$details,
                'name' => $name,
                'slug' => $this->uniqueSlug($input['slug'] ?? $name),
                'type' => 'place',
                'description' => $input['description'] ?? null,
                'is_active' => (bool) ($input['is_active'] ?? true),
                'is_featured' => (bool) ($input['is_featured'] ?? false),
                'allowed_pickup' => (bool) ($input['allowed_pickup'] ?? false),
                'sort_order' => (int) ($input['sort_order'] ?? 0),
                'details_fetched_at' => now(),
            ]);
            $this->enforceFlags($location);
            activity('locations')->causedBy($actorId ? \App\Models\User::find($actorId) : null)->performedOn($location)->log('location_created');

            return $location->fresh();
        });
    }

    public function update(Location $location, array $input, ?int $actorId = null): Location
    {
        return DB::transaction(function () use ($location, $input, $actorId): Location {
            $wasAllowedPickup = $location->allowed_pickup;
            $location->fill([
                'name' => $input['name'] ?? $location->name,
                'slug' => isset($input['slug']) ? $this->uniqueSlug($input['slug'], $location->id) : $location->slug,
                'description' => $input['description'] ?? $location->description,
                'is_active' => $input['is_active'] ?? $location->is_active,
                'is_featured' => $input['is_featured'] ?? $location->is_featured,
                'allowed_pickup' => $input['allowed_pickup'] ?? $location->allowed_pickup,
                'sort_order' => $input['sort_order'] ?? $location->sort_order,
            ]);
            $this->enforceFlags($location, $wasAllowedPickup);
            $location->save();
            activity('locations')->causedBy($actorId ? \App\Models\User::find($actorId) : null)->performedOn($location)->log('location_updated');

            return $location;
        });
    }

    public function refresh(Location $location, ?int $actorId = null): Location
    {
        $details = $this->maps->catalogueLocationDetails($location->google_place_id);
        $location->forceFill([...$details, 'details_fetched_at' => now()])->save();
        activity('locations')->causedBy($actorId ? \App\Models\User::find($actorId) : null)->performedOn($location)->log('location_details_refreshed');

        return $location;
    }

    public function delete(Location $location, ?int $actorId = null): void
    {
        if ($this->settings->nullableInt('required_location_id') === $location->id) {
            throw new RuntimeException('This location is required by the service area rule. Change that setting before deleting it.');
        }
        if (TripLocation::query()->where('location_id', $location->id)->exists()) {
            throw new RuntimeException('This location is used by a trip. Remove it from the trip locations before deleting it.');
        }
        $this->assertNotLastAllowedPickup($location, $location->allowed_pickup);
        $location->delete();
        activity('locations')->causedBy($actorId ? \App\Models\User::find($actorId) : null)->performedOn($location)->log('location_deleted');
    }

    private function enforceFlags(Location $location, bool $wasAllowedPickup = false): void
    {
        if (! $location->is_active) {
            $hadFlags = $location->is_featured || $location->allowed_pickup;
            if ($wasAllowedPickup || $location->allowed_pickup) {
                $this->assertNotLastAllowedPickup($location);
            }
            $location->is_featured = false;
            $location->allowed_pickup = false;
            if ($hadFlags) activity('locations')->performedOn($location)->log('location_flags_cleared');
        }

        if (($location->is_featured || $location->allowed_pickup) && ! $location->is_active) {
            throw new RuntimeException('Only active locations can be featured or used as allowed pickups.');
        }

        if ($wasAllowedPickup && ! $location->allowed_pickup) {
            $this->assertNotLastAllowedPickup($location, $wasAllowedPickup);
        }
    }

    private function assertNotLastAllowedPickup(Location $location, bool $wasAllowedPickup = true): void
    {
        if (! $this->settings->bool('restrict_pickups_enabled') || ! $wasAllowedPickup) return;
        $remaining = Location::query()->allowedPickup()->whereKeyNot($location->id)->exists();
        if (! $remaining) throw new RuntimeException('Pickup restriction is enabled, so at least one active allowed pickup must remain.');
    }

    private function uniqueSlug(string $value, ?int $exceptId = null): string
    {
        $base = Str::slug($value) ?: 'location'; $slug = $base; $suffix = 2;
        while (Location::withTrashed()->where('slug', $slug)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists()) $slug = $base.'-'.++$suffix;
        return $slug;
    }
}
