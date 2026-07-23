<?php

namespace App\Enums;

/**
 * Booking lifecycle status. Kept separate from payment status on purpose.
 *
 * Allowed transitions follow §10 of the master prompt, plus two guarded
 * additions documented in §19/§21:
 *   - HELD -> CANCELLED        (a hold may be cancelled and inventory released)
 *   - EXPIRED -> CONFIRMED      (late-payment recovery after re-checking inventory)
 *   - EXPIRED -> PAYMENT_REVIEW (late payment with no inventory available)
 * The guards themselves live in the booking/payment services, not here.
 */
enum BookingStatus: string
{
    case HELD = 'held';
    case PENDING_PAYMENT = 'pending_payment';
    case CONFIRMED = 'confirmed';
    case PAYMENT_REVIEW = 'payment_review';
    case CHECKED_IN = 'checked_in';
    case CHECKED_OUT = 'checked_out';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case NO_SHOW = 'no_show';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::HELD => [self::PENDING_PAYMENT, self::EXPIRED, self::CANCELLED],
            self::PENDING_PAYMENT => [self::CONFIRMED, self::PAYMENT_REVIEW, self::EXPIRED, self::CANCELLED],
            self::PAYMENT_REVIEW => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::CHECKED_IN, self::CANCELLED, self::NO_SHOW],
            self::CHECKED_IN => [self::CHECKED_OUT],
            self::EXPIRED => [self::CONFIRMED, self::PAYMENT_REVIEW],
            self::CHECKED_OUT, self::CANCELLED, self::NO_SHOW => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Statuses that currently hold or occupy inventory (not yet released/settled).
     */
    public function occupiesInventory(): bool
    {
        return in_array($this, [
            self::HELD,
            self::PENDING_PAYMENT,
            self::CONFIRMED,
            self::PAYMENT_REVIEW,
            self::CHECKED_IN,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::HELD => 'Ditahan',
            self::PENDING_PAYMENT => 'Menunggu Pembayaran',
            self::CONFIRMED => 'Terkonfirmasi',
            self::PAYMENT_REVIEW => 'Peninjauan Pembayaran',
            self::CHECKED_IN => 'Check-in',
            self::CHECKED_OUT => 'Check-out',
            self::CANCELLED => 'Dibatalkan',
            self::EXPIRED => 'Kedaluwarsa',
            self::NO_SHOW => 'Tidak Hadir',
        };
    }

    /**
     * Full Tailwind utility classes (not interpolated fragments) so the JIT
     * compiler can find them — app/ is included in the CSS @source globs.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::HELD, self::PENDING_PAYMENT => 'bg-amber-100 text-amber-700',
            self::CONFIRMED, self::CHECKED_IN => 'bg-emerald-100 text-emerald-700',
            self::PAYMENT_REVIEW => 'bg-orange-100 text-orange-700',
            self::CHECKED_OUT => 'bg-sky-100 text-sky-700',
            self::CANCELLED, self::EXPIRED, self::NO_SHOW => 'bg-rose-100 text-rose-700',
        };
    }
}
