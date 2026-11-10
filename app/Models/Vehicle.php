<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\InteractsWithMedia;

class Vehicle extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity, SoftDeletes;

    /** Amenities that may be advertised consistently across the website. */
    public const AMENITIES = [
        'Wi-Fi',
        'Bottled water',
        'Child seat',
        'Phone charging',
        'Leather interior',
        'Climate control',
        'Privacy glass',
        'Wheelchair accessible',
    ];

    protected $fillable = [
        'name', 'model_name', 'slug', 'category_id', 'description', 'passenger_capacity',
        'luggage_capacity', 'price_per_km', 'minimum_fare', 'amenities',
        'slot_check_enabled', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'slot_check_enabled' => 'boolean',
            'is_active' => 'boolean',
            'price_per_km' => 'integer',
            'minimum_fare' => 'integer',
        ];
    }

    public function fleetUnits(): HasMany
    {
        return $this->hasMany(FleetUnit::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'category_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    /** Bookable online: active, priced, and either no slot check or at least one unit. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereHas('category', fn (Builder $categories) => $categories->active())
            ->whereNotNull('price_per_km')
            ->where(fn (Builder $q) => $q
                ->where('slot_check_enabled', false)
                ->orWhereHas('fleetUnits'));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')->acceptsMimeTypes([
            'image/jpeg', 'image/png', 'image/webp',
        ]);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->fit(Fit::Crop, 480, 320)
            ->quality(82)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('web')
            ->fit(Fit::Max, 1600, 1200)
            ->quality(85)
            ->format('webp')
            ->nonQueued();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
