<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class VehicleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $primary = $this->media->where('collection_name', 'images')->sortBy('order_column')->first();
        $imageUrl = $primary?->hasGeneratedConversion('web') ? $primary->getUrl('web') : $primary?->getUrl();
        $thumbnailUrl = $primary?->hasGeneratedConversion('thumbnail') ? $primary->getUrl('thumbnail') : $imageUrl;

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
            'price_per_km' => $this->price_per_km,
            'minimum_fare' => $this->minimum_fare,
            'price_on_request' => $this->price_per_km === null,
            'amenities' => $this->amenities ?? [],
            'image' => $primary === null ? null : [
                'url' => $imageUrl,
                'thumbnail_url' => $thumbnailUrl,
            ],
        ];
    }
}
