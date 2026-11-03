<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripAddon extends Model
{
    protected $fillable = ['trip_id', 'name', 'description', 'price_minor', 'max_quantity', 'is_active', 'sort_order'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function trip(): BelongsTo { return $this->belongsTo(Trip::class); }
}
