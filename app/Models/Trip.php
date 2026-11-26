<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Trip extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'slug', 'title', 'subtitle', 'summary', 'description', 'region', 'status',
        'pricing_mode', 'fixed_departures_only', 'meeting_point_required', 'pickup_locations_only', 'min_travellers', 'max_travellers', 'traveller_limits_enabled', 'is_featured', 'sort_order', 'meta_title',
        'meta_description', 'published_at', 'content_data',
    ];

    protected function casts(): array
    {
        return ['content_data' => 'array', 'is_featured' => 'boolean', 'fixed_departures_only' => 'boolean', 'meeting_point_required' => 'boolean', 'pickup_locations_only' => 'boolean', 'traveller_limits_enabled' => 'boolean', 'published_at' => 'datetime'];
    }

    public function departures(): HasMany { return $this->hasMany(TripDeparture::class)->orderBy('sort_order'); }
    public function items(): HasMany { return $this->hasMany(TripItem::class)->orderBy('kind')->orderBy('sort_order'); }
    public function addons(): HasMany { return $this->hasMany(TripAddon::class)->orderBy('sort_order'); }
    public function prices(): HasMany { return $this->hasMany(TripPrice::class); }
    public function blackouts(): HasMany { return $this->hasMany(TripBlackoutDate::class); }
    public function enquiries(): HasMany { return $this->hasMany(Enquiry::class); }
    public function bookings(): HasMany { return $this->hasMany(Booking::class); }
    public function tripLocations(): HasMany { return $this->hasMany(TripLocation::class)->orderBy('sort_order'); }

    public function scopePublished(Builder $query): Builder { return $query->where('status', 'published'); }

    /** Return the original cover URL, or null when no cover has been uploaded. */
    public function coverUrl(): ?string
    {
        return $this->getFirstMediaUrl('cover') ?: null;
    }

    /** Return gallery media in the display order configured by the admin. */
    public function galleryMedia(): MediaCollection
    {
        return $this->getMedia('gallery')->sortBy('order_column')->values();
    }

    /** Return the cover thumbnail, or null when no cover has been uploaded. */
    public function thumbnailUrl(): ?string
    {
        $cover = $this->getFirstMedia('cover');

        if ($cover === null) {
            return null;
        }

        return $cover->hasGeneratedConversion('thumbnail')
            ? $cover->getUrl('thumbnail')
            : $cover->getUrl();
    }

    /**
     * The booking keeps this small, customer-facing snapshot so later edits to a
     * trip do not change the trip identity shown for a completed booking.
     *
     * @return array<string, mixed>
     */
    public function bookingSnapshot(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'summary' => $this->summary,
            'region' => $this->region,
            'slug' => $this->slug,
            'pricing_mode' => $this->pricing_mode,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
        $this->addMediaCollection('gallery')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->fit(Fit::Crop, 480, 320)
            ->quality(82)
            ->format('webp')
            ->nonQueued();
    }

    public function faqs(){
    return $this->hasMany(TripFaq::class)->orderBy('sort_order');
}

}
