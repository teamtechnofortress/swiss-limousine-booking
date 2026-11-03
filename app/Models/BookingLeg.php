<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingLeg extends Model
{
    protected $fillable = [
        'booking_id', 'direction', 'pickup', 'dropoff', 'starts_at', 'distance_m', 'duration_s', 'amount',
        'pickup_location_id', 'dropoff_location_id', 'pickup_override', 'pickup_override_reason',
    ];

    protected function casts(): array
    {
        return [
            'pickup' => 'array',
            'dropoff' => 'array',
            'starts_at' => 'immutable_datetime',
            'pickup_override' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function pickupLocation(): BelongsTo { return $this->belongsTo(Location::class, 'pickup_location_id')->withTrashed(); }
    public function dropoffLocation(): BelongsTo { return $this->belongsTo(Location::class, 'dropoff_location_id')->withTrashed(); }
}
