<?php

namespace App\Services\Payments;

use App\Enums\AuditAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingInventoryService;
use RuntimeException;

/**
 * Manual resolution of bookings parked in PAYMENT_REVIEW (amount mismatch, late
 * payment with no inventory, unrecognised provider status). Every override is
 * audited with the admin's reason (§21, §33).
 */
class PaymentReviewService
{
    public function __construct(
        private readonly BookingInventoryService $inventory,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Approve a reviewed payment and confirm the booking, re-checking inventory
     * under lock first (the room may have been sold in the meantime).
     */
    public function approve(Booking $booking, string $reason): void
    {
        if (trim($reason) === '') {
            throw new RuntimeException('Alasan wajib diisi untuk persetujuan manual.');
        }

        $this->inventory->transactionWithRetry(function () use ($booking, $reason) {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if ($locked->status !== BookingStatus::PAYMENT_REVIEW) {
                throw new RuntimeException('Pemesanan ini tidak berada dalam status peninjauan pembayaran.');
            }

            // Rooms parked as held for this very booking are already ours: settle
            // them. Re-checking capacity here would count the booking's own hold
            // against itself and refuse the approval on a sold-out date.
            $stillHeld = $locked->review_inventory_held;

            foreach ($locked->items as $item) {
                $roomType = $item->roomType;
                if (! $roomType) {
                    continue;
                }

                $rows = $this->inventory->lockRows($roomType, $locked->stayPeriod());

                if ($stillHeld) {
                    $this->inventory->convertHeldToConfirmed($rows, $item->rooms);

                    continue;
                }

                // Late payment after the hold lapsed — the rooms went back on
                // sale, so they have to be available all over again.
                if (! $this->inventory->hasCapacity($rows, $locked->stayPeriod(), $item->rooms, $roomType->sellableRoomCount())) {
                    throw new RuntimeException('Inventaris tidak mencukupi untuk mengonfirmasi pemesanan ini.');
                }

                $this->inventory->increaseConfirmed($rows, $item->rooms);
            }

            $locked->transitionTo(BookingStatus::CONFIRMED);
            $locked->payment_status = PaymentStatus::PAID;
            $locked->confirmed_at = now();
            $locked->review_inventory_held = false;
            $locked->save();

            $this->audit->log(AuditAction::ADMIN_OVERRIDE->value, $locked, null, [
                'action' => 'payment_review_approved',
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Reject the reviewed payment and cancel the booking. Any refund is recorded
     * separately — cancellation is never treated as a completed refund (§22).
     */
    public function reject(Booking $booking, string $reason): void
    {
        if (trim($reason) === '') {
            throw new RuntimeException('Alasan wajib diisi untuk penolakan.');
        }

        $this->inventory->transactionWithRetry(function () use ($booking, $reason) {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if ($locked->status !== BookingStatus::PAYMENT_REVIEW) {
                throw new RuntimeException('Pemesanan ini tidak berada dalam status peninjauan pembayaran.');
            }

            // Rejecting must put the rooms back on sale. Nothing else will:
            // the hold sweeper ignores PAYMENT_REVIEW, so a hold left behind
            // here blocks those dates forever.
            if ($locked->review_inventory_held) {
                foreach ($locked->items as $item) {
                    if ($roomType = $item->roomType) {
                        $rows = $this->inventory->lockRows($roomType, $locked->stayPeriod());
                        $this->inventory->releaseHeld($rows, $item->rooms);
                    }
                }
                $locked->review_inventory_held = false;
            }

            $locked->transitionTo(BookingStatus::CANCELLED);
            $locked->cancelled_at = now();
            $locked->cancellation_reason = $reason;
            // Money already received stays flagged for refund handling.
            if ($locked->payment_status === PaymentStatus::REVIEW) {
                $locked->payment_status = PaymentStatus::REFUND_PENDING;
            }
            $locked->save();

            $this->audit->log(AuditAction::ADMIN_OVERRIDE->value, $locked, null, [
                'action' => 'payment_review_rejected',
                'reason' => $reason,
            ]);
        });
    }
}
