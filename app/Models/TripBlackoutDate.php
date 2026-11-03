<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripBlackoutDate extends Model
{
    protected $fillable = ['trip_id', 'date', 'reason'];
    protected function casts(): array { return ['date' => 'date']; }
    public function trip(): BelongsTo { return $this->belongsTo(Trip::class); }
}
