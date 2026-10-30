<?php

namespace App\Enums;

/** Lifecycle of one provider Checkout Session. A booking may have many attempts. */
enum PaymentAttemptStatus: string
{
    case Creating = 'creating';
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Failed = 'failed';
    case Superseded = 'superseded';

    public function isOpen(): bool
    {
        return in_array($this, [self::Creating, self::Pending], true);
    }
}
