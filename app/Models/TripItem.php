<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripItem extends Model
{
    protected $fillable = ['trip_id', 'kind', 'title', 'description', 'sort_order'];
    public function trip(): BelongsTo { return $this->belongsTo(Trip::class); }
}
