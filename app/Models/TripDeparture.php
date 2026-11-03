<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripDeparture extends Model
{
    protected $fillable = ['trip_id', 'start_time', 'sort_order', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function trip(): BelongsTo { return $this->belongsTo(Trip::class); }
}
