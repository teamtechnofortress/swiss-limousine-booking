<?php

namespace App\Models;

use App\Enums\VehicleCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Vehicle extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'model_name', 'slug', 'category', 'description', 'passenger_capacity',
        'luggage_capacity', 'price_per_km', 'minimum_fare', 'amenities',
        'slot_check_enabled', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => VehicleCategory::class,
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

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Bookable online: active, priced, and either no slot check or at least one unit. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('price_per_km')
            ->where(fn (Builder $q) => $q
                ->where('slot_check_enabled', false)
                ->orWhereHas('fleetUnits'));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
