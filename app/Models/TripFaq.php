<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripFaq extends Model
{
    protected $fillable = [
        'trip_id',
        'question',
        'answer',
        'sort_order',
        'is_active',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
