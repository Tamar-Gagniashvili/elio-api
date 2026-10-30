<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Created = 'created';
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case RefundRequested = 'refund_requested';
    case Refunded = 'refunded';
    case RefundedPartially = 'refunded_partially';
    case Expired = 'expired';

    public static function flittStatus(string $flittStatus, float $reversalAmount = 0): self
    {
        return match ($flittStatus) {
            'created' => self::Created,
            'processing' => self::Processing,
            'approved' => $reversalAmount > 0 ? self::RefundedPartially : self::Completed,
            'declined' => self::Rejected,
            'expired' => self::Expired,
            'reversed' => self::Refunded,
            default => self::Processing,
        };
    }

    /**
     * Whether a callback alone is enough to make this change. A settled payment normally only
     * moves on to a refund state, so anything else is either a late or out-of-order callback
     * (e.g. the "declined" of an earlier card attempt) or a genuine reversal by the bank; the
     * two look identical, so the caller must confirm the order's current state with the gateway.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Completed => in_array($next, [self::Completed, self::RefundedPartially, self::Refunded], true),
            self::RefundRequested, self::RefundedPartially => in_array($next, [self::RefundedPartially, self::Refunded], true),
            self::Refunded => $next === self::Refunded,
            default => true,
        };
    }

    public static function bogStatus(string $bogStatus): self
    {
        return match ($bogStatus) {
            'created' => self::Created,
            'in_progress' => self::Processing,
            'completed' => self::Completed,
            'refunded' => self::Refunded,
            'partially_refunded' => self::RefundedPartially,
            'rejected', 'failed' => self::Rejected,
            'expired' => self::Expired,
            default => self::Processing,
        };
    }
}
