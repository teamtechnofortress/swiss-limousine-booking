<?php

namespace App\Models;

use App\Enums\BlockType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ScheduleBlock extends Model
{
    protected $fillable = [
        'vehicle_id', 'fleet_unit_id', 'booking_id', 'booking_leg_id',
        'block_type', 'starts_at', 'ends_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'block_type' => BlockType::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** Blocks whose occupied window overlaps [from, to). */
    public function scopeOverlapping(Builder $query, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }
}
