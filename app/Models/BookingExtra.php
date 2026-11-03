<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingExtra extends Model
{
    protected $fillable = ['booking_id', 'trip_addon_id', 'name', 'price_minor', 'quantity', 'total_minor'];
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function tripAddon(): BelongsTo { return $this->belongsTo(TripAddon::class); }
}
