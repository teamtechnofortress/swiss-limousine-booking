<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripLocation extends Model
{
    public const PICKUP = 'pickup';
    public const MAIN_DESTINATION = 'main_destination';
    public const STOP = 'stop';

    protected $fillable = ['trip_id', 'location_id', 'role', 'sort_order'];

    public function trip(): BelongsTo { return $this->belongsTo(Trip::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class)->withTrashed(); }
}
