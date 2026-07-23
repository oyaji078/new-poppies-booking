<?php

namespace App\Enums;

/**
 * Status of a single payment attempt (one DOKU checkout session).
 * A booking may accumulate several attempts; only one may be active at a time.
 */
enum PaymentAttemptStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    /**
     * "Active" = still awaiting a result and blocking new attempts.
     */
    public function isActive(): bool
    {
        return $this === self::PENDING;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::PAID, self::FAILED, self::EXPIRED, self::CANCELLED], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu',
            self::PAID => 'Berhasil',
            self::FAILED => 'Gagal',
            self::EXPIRED => 'Kedaluwarsa',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
