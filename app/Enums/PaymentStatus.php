<?php

namespace App\Enums;

/**
 * Booking-level payment status (the settled view of money for a booking).
 * Individual gateway attempts use {@see PaymentAttemptStatus}.
 */
enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case REVIEW = 'review';
    case REFUND_PENDING = 'refund_pending';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case REFUNDED = 'refunded';

    public function isPaid(): bool
    {
        return in_array($this, [self::PAID, self::PARTIALLY_REFUNDED, self::REFUNDED, self::REFUND_PENDING], true);
    }

    public function isRefundable(): bool
    {
        // An unpaid booking can never be refunded.
        return in_array($this, [self::PAID, self::PARTIALLY_REFUNDED], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Belum Dibayar',
            self::PENDING => 'Menunggu',
            self::PAID => 'Lunas',
            self::FAILED => 'Gagal',
            self::EXPIRED => 'Kedaluwarsa',
            self::REVIEW => 'Peninjauan',
            self::REFUND_PENDING => 'Refund Diproses',
            self::PARTIALLY_REFUNDED => 'Refund Sebagian',
            self::REFUNDED => 'Refund Penuh',
        };
    }

    /**
     * Full Tailwind utility classes so the JIT compiler can find them.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::PAID => 'bg-emerald-100 text-emerald-700',
            self::PENDING => 'bg-amber-100 text-amber-700',
            self::REVIEW, self::REFUND_PENDING, self::PARTIALLY_REFUNDED => 'bg-orange-100 text-orange-700',
            self::UNPAID => 'bg-slate-100 text-slate-600',
            self::FAILED, self::EXPIRED => 'bg-rose-100 text-rose-700',
            self::REFUNDED => 'bg-sky-100 text-sky-700',
        };
    }
}
