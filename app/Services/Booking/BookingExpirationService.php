<?php

namespace App\Services\Booking;

use App\Enums\AuditAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Services\Audit\AuditLogger;

/**
 * Expires lapsed booking holds and returns their inventory to the pool.
 * Runs every minute from the scheduler. Safe to run repeatedly: a booking is
 * only expired once because the status check happens under the same lock.
 */
class BookingExpirationService
{
    public function __construct(
        private readonly BookingInventoryService $inventory,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return int number of bookings expired
     */
    public function expireDueHolds(int $limit = 200): int
    {
        $due = Booking::query()->expiredHolds()->limit($limit)->pluck('id');
        $expired = 0;

        foreach ($due as $bookingId) {
            if ($this->expire($bookingId)) {
                $expired++;
            }
        }

        return $expired;
    }

    /**
     * Expire one booking atomically. Returns false when it was already handled.
     */
    public function expire(int $bookingId): bool
    {
        return $this->inventory->transactionWithRetry(function () use ($bookingId) {
            /** @var Booking|null $booking */
            $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->first();

            if (! $booking) {
                return false;
            }

            // Re-check under the lock — another worker may have won the race.
            $stillHeld = in_array($booking->status, [BookingStatus::HELD, BookingStatus::PENDING_PAYMENT], true);
            if (! $stillHeld || ! $booking->isHoldExpired()) {
                return false;
            }

            foreach ($booking->items as $item) {
                $roomType = $item->roomType;
                if (! $roomType) {
                    continue;
                }

                $lockedRows = $this->inventory->lockRows($roomType, $booking->stayPeriod());
                $this->inventory->releaseHeld($lockedRows, $item->rooms);
            }

            $booking->transitionTo(BookingStatus::EXPIRED);
            $booking->payment_status = PaymentStatus::EXPIRED;
            $booking->save();

            // Any still-pending payment attempt is dead once the hold lapses.
            $booking->paymentAttempts()
                ->where('status', PaymentAttemptStatus::PENDING->value)
                ->update(['status' => PaymentAttemptStatus::EXPIRED->value]);

            $this->audit->log(AuditAction::BOOKING_EXPIRED->value, $booking, null, [
                'code' => $booking->code,
                'released_rooms' => $booking->rooms,
            ]);

            return true;
        });
    }
}
