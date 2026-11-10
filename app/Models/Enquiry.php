<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Enquiry extends Model
{
    public const TYPES = ['general', 'trip', 'vehicle', 'booking', 'corporate'];

    public const STATUSES = ['new', 'contacted', 'quoted', 'confirmed', 'closed', 'spam'];

    protected $fillable = [
        'reference', 'type', 'status', 'read_at', 'trip_id', 'vehicle_id', 'booking_id', 'subject_title', 'source_url',
        'customer_name', 'customer_email', 'customer_phone', 'preferred_contact_method',
        'travel_date', 'return_date', 'passengers', 'luggage', 'pickup_location', 'dropoff_location', 'message',
        'assigned_to', 'handled_at', 'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
            'return_date' => 'date',
            'read_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $enquiry): void {
            if ($enquiry->reference === null) {
                $enquiry->reference = 'ENQ-'.Str::upper((string) Str::ulid());
            }
        });
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class)->withTrashed();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
