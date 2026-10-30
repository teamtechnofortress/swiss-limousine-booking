<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A durable record of one hosted-payment session and its provider events. */
class PaymentAttempt extends Model
{
    protected $fillable = [
        'payment_id', 'booking_id', 'provider', 'provider_reference', 'provider_payment_intent',
        'idempotency_key', 'status', 'is_current', 'expires_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'is_current' => 'boolean',
            'expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
