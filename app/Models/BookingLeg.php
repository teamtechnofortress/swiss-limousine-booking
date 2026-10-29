<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingLeg extends Model
{
    protected $fillable = [
        'booking_id', 'direction', 'pickup', 'dropoff', 'starts_at', 'distance_m', 'duration_s', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'pickup' => 'array',
            'dropoff' => 'array',
            'starts_at' => 'immutable_datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
