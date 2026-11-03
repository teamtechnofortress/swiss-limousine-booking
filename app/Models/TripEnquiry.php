<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripEnquiry extends Model
{
    public $timestamps = false;
    protected $fillable = ['trip_id', 'name', 'email', 'phone', 'travel_date', 'travellers', 'message', 'status', 'handled_by', 'handled_at'];
    protected function casts(): array { return ['travel_date' => 'date', 'handled_at' => 'datetime']; }
    public function trip(): BelongsTo { return $this->belongsTo(Trip::class); }
    public function handledBy(): BelongsTo { return $this->belongsTo(User::class, 'handled_by'); }
}
