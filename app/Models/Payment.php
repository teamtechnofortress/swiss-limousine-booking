<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'booking_id', 'method', 'provider', 'provider_reference', 'provider_payment_intent', 'provider_charge_reference',
        'provider_balance_transaction_reference', 'provider_balance_amount', 'provider_balance_currency',
        'provider_fee_amount', 'provider_fee_details', 'provider_net_amount', 'provider_exchange_rate',
        'provider_ledger_synced_at', 'gross_amount', 'vat_amount', 'currency', 'status', 'paid_at', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'provider_fee_details' => 'array',
            'provider_ledger_synced_at' => 'datetime',
            'provider_exchange_rate' => 'decimal:10',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
