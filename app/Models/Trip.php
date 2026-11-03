<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Trip extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'slug', 'title', 'subtitle', 'summary', 'description', 'region', 'duration_minutes', 'status',
        'pricing_mode', 'fixed_departures_only', 'meeting_point_required', 'pickup_locations_only', 'min_travellers', 'max_travellers', 'is_featured', 'sort_order', 'meta_title',
        'meta_description', 'published_at',
    ];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'fixed_departures_only' => 'boolean', 'meeting_point_required' => 'boolean', 'pickup_locations_only' => 'boolean', 'published_at' => 'datetime'];
    }

    public function departures(): HasMany { return $this->hasMany(TripDeparture::class)->orderBy('sort_order'); }
    public function items(): HasMany { return $this->hasMany(TripItem::class)->orderBy('kind')->orderBy('sort_order'); }
    public function addons(): HasMany { return $this->hasMany(TripAddon::class)->orderBy('sort_order'); }
    public function prices(): HasMany { return $this->hasMany(TripPrice::class); }
    public function blackouts(): HasMany { return $this->hasMany(TripBlackoutDate::class); }
    public function enquiries(): HasMany { return $this->hasMany(TripEnquiry::class); }
    public function bookings(): HasMany { return $this->hasMany(Booking::class); }
    public function tripLocations(): HasMany { return $this->hasMany(TripLocation::class)->orderBy('sort_order'); }

    public function scopePublished(Builder $query): Builder { return $query->where('status', 'published'); }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
        $this->addMediaCollection('gallery')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }
}
