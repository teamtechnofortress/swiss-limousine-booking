<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class VehicleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $primary = $this->resource->primaryImage();
        $imageUrl = $this->resource->primaryImageUrl();
        $thumbnailUrl = $this->resource->thumbnailUrl();
        $cover = $primary === null ? null : [
            'id' => $primary->id,
            'url' => $imageUrl,
            'thumbnail_url' => $thumbnailUrl,
            'alt' => $this->name,
        ];
        $gallery = $this->resource->galleryMedia()->map(function ($media): array {
            $url = $media->hasGeneratedConversion('web') ? $media->getUrl('web') : $media->getUrl();

            return [
                'id' => $media->id,
                'url' => $url,
                'thumbnail_url' => $media->hasGeneratedConversion('thumbnail') ? $media->getUrl('thumbnail') : $url,
                'alt' => $this->name,
            ];
        })->values();
        $rangePricing = $this->pricing_mode === 'range';
        $displayFare = $rangePricing ? $this->range_base_fare : $this->minimum_fare;
        $bookableOnline = ($rangePricing || $this->price_per_km !== null) && (! $this->slot_check_enabled || (int) ($this->fleet_units_count ?? 0) > 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'logo_url' => $this->category->logoUrl(),
            ],
            'description' => $this->description,
            'passenger_capacity' => $this->passenger_capacity,
            'luggage_capacity' => $this->luggage_capacity,
            'capacity' => [
                'passengers' => $this->passenger_capacity,
                'luggage' => $this->luggage_capacity,
            ],
            // Public fleet cards show an entry fare, never the internal km rates.
            'display_fare_minor' => $displayFare,
            'display_fare_type' => $rangePricing ? 'base' : 'minimum',
            'price_on_request' => ! $rangePricing && $this->price_per_km === null,
            'pricing' => [
                'mode' => $rangePricing ? 'distance_range' : ($this->price_per_km === null ? 'enquiry' : 'distance'),
                'display_fare_minor' => $displayFare,
                'display_fare_type' => $rangePricing ? 'base' : 'minimum',
                'minimum_fare_minor' => $rangePricing ? null : $this->minimum_fare,
                'base_distance_m' => $rangePricing ? $this->range_base_distance_m : null,
                'base_distance_km' => $rangePricing ? $this->range_base_distance_m / 1000 : null,
                'base_fare_minor' => $rangePricing ? $this->range_base_fare : null,
            ],
            'availability' => [
                'online_booking_enabled' => $bookableOnline,
                'slot_check_enabled' => (bool) $this->slot_check_enabled,
            ],
            'amenities' => $this->amenities ?? [],
            'media' => [
                'cover' => $cover,
                'gallery' => $gallery,
            ],
            // Keep the original fields available for existing frontend clients.
            'image' => $cover,
            'gallery' => $gallery,
        ];
    }
}
