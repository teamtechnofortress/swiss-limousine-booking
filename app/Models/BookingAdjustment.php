<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingAdjustment extends Model
{
    protected $fillable = [
        'booking_id', 'type', 'minutes', 'gross_amount', 'vat_amount', 'status', 'reason', 'created_by',
    ];
}
