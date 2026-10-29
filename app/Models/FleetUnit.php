<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetUnit extends Model
{
    protected $fillable = ['vehicle_id', 'plate'];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    protected function plate(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper(preg_replace('/\s+/', '', $value) ?? ''),
        );
    }
}
