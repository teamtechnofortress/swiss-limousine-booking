<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class VehicleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'model_name' => $this->model_name,
            'slug' => $this->slug,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'logo_url' => $this->category->getFirstMediaUrl('logo', 'icon') ?: null,
            ]),
            'description' => $this->description,
            'passenger_capacity' => $this->passenger_capacity,
            'luggage_capacity' => $this->luggage_capacity,
            'price_per_km' => $this->price_per_km,
            'minimum_fare' => $this->minimum_fare,
            'amenities' => $this->amenities ?? [],
            'slot_check_enabled' => $this->slot_check_enabled,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'bookable_online' => $this->is_active && $this->price_per_km !== null && (! $this->slot_check_enabled || $this->fleetUnits->isNotEmpty()),
            'units' => $this->whenLoaded('fleetUnits', fn () => $this->fleetUnits->map(fn ($unit) => [
                'id' => $unit->id,
                'plate' => $unit->plate,
                'has_booking_history' => $unit->bookings()->withTrashed()->exists(),
            ])->values()),
            'images' => $this->whenLoaded('media', fn () => $this->media
                ->where('collection_name', 'images')
                ->sortBy('order_column')
                ->values()
                ->map(function ($media): array {
                    // Images uploaded before conversions were introduced do not
                    // have web/thumbnail files yet. Keep them usable meanwhile.
                    $webUrl = $media->hasGeneratedConversion('web') ? $media->getUrl('web') : $media->getUrl();

                    return [
                        'id' => $media->id,
                        'url' => $webUrl,
                        'thumbnail_url' => $media->hasGeneratedConversion('thumbnail') ? $media->getUrl('thumbnail') : $webUrl,
                        'name' => $media->name,
                        'order' => $media->order_column,
                    ];
                })),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
