<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VehicleDistancePriceRange extends Model
{
    protected $fillable = [
        'start_distance_m', 'end_distance_m', 'minimum_amount', 'price_per_km', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'start_distance_m' => 'integer',
            'end_distance_m' => 'integer',
            'minimum_amount' => 'integer',
            'price_per_km' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
