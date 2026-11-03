<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Location extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'type', 'description', 'google_place_id', 'formatted_address', 'country_code', 'locality',
        'admin_area_code', 'admin_area_name', 'latitude', 'longitude', 'is_featured', 'allowed_pickup',
        'sort_order', 'is_active', 'details_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float', 'longitude' => 'float', 'is_active' => 'boolean',
            'is_featured' => 'boolean', 'allowed_pickup' => 'boolean', 'details_fetched_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAllowedPickup(Builder $query): Builder
    {
        return $query->active()->where('allowed_pickup', true);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('hero')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }
}
