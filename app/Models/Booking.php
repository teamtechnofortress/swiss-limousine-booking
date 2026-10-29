<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Booking extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'reference', 'status', 'source', 'payment_method', 'vehicle_id', 'fleet_unit_id',
        'customer_name', 'customer_email', 'customer_phone', 'passengers', 'luggage', 'flight_number',
        'currency', 'taxable_amount', 'vat_rate_bp', 'vat_amount', 'total_amount', 'vat_included',
        'vat_registration_number', 'price_breakdown', 'access_token_hash', 'verification_token_hash',
        'hold_expires_at', 'verified_at', 'paid_at', 'admin_seen_at',
        'cancellation_requested_at', 'cancellation_reason', 'cancelled_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'payment_method' => PaymentMethod::class,
            'vat_included' => 'boolean',
            'price_breakdown' => 'array',
            'hold_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'paid_at' => 'datetime',
            'admin_seen_at' => 'datetime',
            'cancellation_requested_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** withTrashed: a booking must still show its vehicle after the vehicle is retired. */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function fleetUnit(): BelongsTo
    {
        return $this->belongsTo(FleetUnit::class);
    }

    public function legs(): HasMany
    {
        return $this->hasMany(BookingLeg::class)->orderBy('starts_at');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ScheduleBlock::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(BookingAdjustment::class);
    }

    public function transitionTo(BookingStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw BookingException::invalidTransition($this->status, $next);
        }

        $this->status = $next;
    }

    /** Needs review first, then unseen confirmed bookings, then everything else, newest first. */
    public function scopeAdminQueue(Builder $query): Builder
    {
        return $query
            ->orderByRaw(
                'CASE WHEN status = ? THEN 0 WHEN admin_seen_at IS NULL AND status = ? THEN 1 ELSE 2 END',
                [BookingStatus::NeedsReview->value, BookingStatus::Confirmed->value],
            )
            ->orderByDesc('created_at');
    }

    public function scopeUnseen(Builder $query): Builder
    {
        return $query
            ->whereNull('admin_seen_at')
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::NeedsReview->value]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'fleet_unit_id', 'admin_seen_at', 'total_amount', 'hold_expires_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
