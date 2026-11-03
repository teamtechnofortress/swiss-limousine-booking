<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripPrice extends Model
{
    protected $fillable = ['trip_id', 'pickup_location_id', 'vehicle_id', 'price_minor', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function trip(): BelongsTo { return $this->belongsTo(Trip::class); }
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class)->withTrashed(); }
    public function pickupLocation(): BelongsTo { return $this->belongsTo(Location::class, 'pickup_location_id')->withTrashed(); }
}
