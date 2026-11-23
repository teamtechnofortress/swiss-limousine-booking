<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class VehicleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $images = $this->media->where('collection_name', 'images')->sortBy('order_column')->values();
        $primary = $images->first();
        $imageUrl = $primary?->hasGeneratedConversion('web') ? $primary->getUrl('web') : $primary?->getUrl();
        $thumbnailUrl = $primary?->hasGeneratedConversion('thumbnail') ? $primary->getUrl('thumbnail') : $imageUrl;
        $cover = $primary === null ? null : [
            'id' => $primary->id,
            'url' => $imageUrl,
            'thumbnail_url' => $thumbnailUrl,
            'alt' => $this->name,
        ];
        $gallery = $images->slice(1)->map(function ($media): array {
            $url = $media->hasGeneratedConversion('web') ? $media->getUrl('web') : $media->getUrl();

            return [
                'id' => $media->id,
                'url' => $url,
                'thumbnail_url' => $media->hasGeneratedConversion('thumbnail') ? $media->getUrl('thumbnail') : $url,
                'alt' => $this->name,
            ];
        })->values();
        $rangePricing = $this->pricing_mode === 'range';
        $bookableOnline = ($rangePricing || $this->price_per_km !== null) && (! $this->slot_check_enabled || (int) ($this->fleet_units_count ?? 0) > 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'logo_url' => $this->category->getFirstMediaUrl('logo', 'icon') ?: null,
            ],
            'description' => $this->description,
            'passenger_capacity' => $this->passenger_capacity,
            'luggage_capacity' => $this->luggage_capacity,
            'capacity' => [
                'passengers' => $this->passenger_capacity,
                'luggage' => $this->luggage_capacity,
            ],
            'price_per_km' => $this->price_per_km,
            'minimum_fare' => $this->minimum_fare,
            'price_on_request' => ! $rangePricing && $this->price_per_km === null,
            'pricing' => [
                'mode' => $rangePricing ? 'distance_range' : ($this->price_per_km === null ? 'enquiry' : 'distance'),
                'price_per_km_minor' => $this->price_per_km,
                'minimum_fare_minor' => $this->minimum_fare,
                'base_distance_m' => $rangePricing ? $this->range_base_distance_m : null,
                'base_distance_km' => $rangePricing ? $this->range_base_distance_m / 1000 : null,
                'base_fare_minor' => $rangePricing ? $this->range_base_fare : null,
                'ranges' => $this->when($rangePricing, fn () => $this->distancePriceRanges->map(fn ($range) => [
                    'start_distance_m' => $range->start_distance_m,
                    'end_distance_m' => $range->end_distance_m,
                    'minimum_amount_minor' => $range->minimum_amount,
                    'price_per_km_minor' => $range->price_per_km,
                ])->values()),
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
