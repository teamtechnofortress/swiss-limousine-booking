<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PendingPayment = 'pending_payment';
    case PendingVerification = 'pending_verification';
    case Confirmed = 'confirmed';
    case NeedsReview = 'needs_review';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case NoShow = 'no_show';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::PendingPayment, self::PendingVerification => [self::Confirmed, self::Expired, self::Cancelled],
            self::Expired => [self::Confirmed, self::NeedsReview],
            self::NeedsReview => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Assigned, self::Cancelled],
            self::Assigned => [self::InProgress, self::Cancelled, self::NoShow],
            self::InProgress => [self::Completed],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function holdsSlot(): bool
    {
        return in_array($this, [self::PendingPayment, self::PendingVerification, self::Confirmed, self::NeedsReview, self::Assigned, self::InProgress], true);
    }
}
