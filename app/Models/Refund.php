<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'payment_id', 'gross_amount', 'vat_amount', 'provider_reference', 'provider_balance_transaction_reference',
        'provider_balance_amount', 'provider_balance_currency', 'provider_fee_amount', 'provider_fee_details',
        'provider_net_amount', 'provider_exchange_rate', 'provider_ledger_synced_at', 'idempotency_key', 'status',
        'reason', 'cancel_booking_on_success', 'booking_cancelled_at', 'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'cancel_booking_on_success' => 'boolean',
            'booking_cancelled_at' => 'datetime',
            'provider_fee_details' => 'array',
            'provider_ledger_synced_at' => 'datetime',
            'provider_exchange_rate' => 'decimal:10',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
