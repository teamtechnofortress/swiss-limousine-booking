<?php

namespace App\Enums;

/** The provider lifecycle of one refund request. */
enum RefundStatus: string
{
    case Pending = 'pending';
    case RequiresAction = 'requires_action';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';

    /** A pending refund reserves its amount so it cannot be requested twice. */
    public function reservesAmount(): bool
    {
        return match ($this) {
            self::Pending, self::RequiresAction => true,
            self::Succeeded, self::Failed, self::Canceled => false,
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed, self::Canceled => true,
            self::Pending, self::RequiresAction => false,
        };
    }
}
