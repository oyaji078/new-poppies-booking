<?php

namespace App\Services\Operations;

use App\Enums\AuditAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingInventoryService;
use App\Services\Settings\SettingService;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Booking cancellation (§21).
 *
 * Cancelling is NOT the same as refunding: this releases inventory and records
 * the refund *eligibility*, but any money movement is a separate Refund record
 * that an admin must complete (§22).
 */
class CancellationService
{
    public function __construct(
        private readonly BookingInventoryService $inventory,
        private readonly SettingService $settings,
        private readonly AuditLogger $audit,
        private readonly RefundService $refunds,
    ) {}

    /**
     * @return array{allowed: bool, reason: ?string}
     */
    public function canCancel(Booking $booking, bool $isAdmin = false): array
    {
        return match ($booking->status) {
            BookingStatus::HELD,
            BookingStatus::PENDING_PAYMENT,
            BookingStatus::CONFIRMED,
            BookingStatus::PAYMENT_REVIEW => ['allowed' => true, 'reason' => null],

            BookingStatus::CHECKED_IN => $isAdmin
                ? ['allowed' => false, 'reason' => 'Tamu sudah check-in. Gunakan proses check-out.']
                : ['allowed' => false, 'reason' => 'Pemesanan yang sudah check-in tidak dapat dibatalkan.'],

            BookingStatus::CHECKED_OUT => ['allowed' => false, 'reason' => 'Pemesanan yang sudah check-out tidak dapat dibatalkan.'],

            default => ['allowed' => false, 'reason' => 'Pemesanan ini tidak dapat dibatalkan.'],
        };
    }

    /**
     * Is the guest inside the free-cancellation window (full refund, no fee)?
     */
    public function isRefundEligible(Booking $booking): bool
    {
        if (! $booking->payment_status->isPaid()) {
            return false;
        }

        return now()->lte($this->freeCancellationDeadline($booking));
    }

    /**
     * Deadline for a free (fee-less) cancellation: check-in time minus the
     * free-cancellation window.
     */
    private function freeCancellationDeadline(Booking $booking): CarbonInterface
    {
        $hours = (int) (data_get($booking->cancellation_policy, 'free_cancellation_hours')
            ?? $this->settings->integer('free_cancellation_hours', 24));

        return $this->checkInMoment($booking)->subHours($hours);
    }

    private function checkInMoment(Booking $booking): CarbonInterface
    {
        return $booking->check_in_date->copy()->setTimeFromTimeString(
            (string) $this->settings->get('check_in_time', '14:00')
        );
    }

    /**
     * How much of a PAID booking is refundable on cancellation, and the fee kept.
     *
     * Tiers (all computed from the amount actually paid, never the quoted total):
     *   - within the free window       → full refund, no fee;
     *   - after the window, pre check-in → refund = paid − fee%, fee configurable;
     *   - at/after check-in            → no refund (a cancelled stay that already
     *                                     started is handled as no-show/check-out).
     *
     * @return array{tier: string, fee_percent: int, fee_amount: int, refund_amount: int, eligible: bool}
     */
    public function refundBreakdown(Booking $booking): array
    {
        $paid = $this->refunds->refundableRemaining($booking);

        $none = ['tier' => 'unpaid', 'fee_percent' => 0, 'fee_amount' => 0, 'refund_amount' => 0, 'eligible' => false];

        if ($paid <= 0) {
            return $none;
        }

        if (now()->lte($this->freeCancellationDeadline($booking))) {
            return ['tier' => 'free_window', 'fee_percent' => 0, 'fee_amount' => 0, 'refund_amount' => $paid, 'eligible' => true];
        }

        if (now()->gte($this->checkInMoment($booking))) {
            return ['tier' => 'after_checkin', 'fee_percent' => 100, 'fee_amount' => $paid, 'refund_amount' => 0, 'eligible' => false];
        }

        $feePercent = max(0, min(100, $this->settings->integer('cancellation_fee_percent', 50)));
        $fee = (int) round($paid * $feePercent / 100);
        $refund = max(0, $paid - $fee);

        return [
            'tier' => 'fee',
            'fee_percent' => $feePercent,
            'fee_amount' => $fee,
            'refund_amount' => $refund,
            'eligible' => $refund > 0,
        ];
    }

    public function cancel(Booking $booking, string $reason, ?User $actor = null, bool $isAdmin = false): CancellationRequest
    {
        if (trim($reason) === '') {
            throw new RuntimeException('Alasan pembatalan wajib diisi.');
        }

        $check = $this->canCancel($booking, $isAdmin);
        if (! $check['allowed']) {
            throw new RuntimeException($check['reason']);
        }

        return $this->inventory->transactionWithRetry(function () use ($booking, $reason, $actor, $isAdmin) {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            $recheck = $this->canCancel($locked, $isAdmin);
            if (! $recheck['allowed']) {
                throw new RuntimeException($recheck['reason']);
            }

            $wasHolding = in_array($locked->status, [BookingStatus::HELD, BookingStatus::PENDING_PAYMENT], true);
            $wasConfirmed = in_array($locked->status, [BookingStatus::CONFIRMED, BookingStatus::PAYMENT_REVIEW], true);

            // Return the rooms to the pool.
            foreach ($locked->items as $item) {
                $roomType = $item->roomType;
                if (! $roomType) {
                    continue;
                }

                $rows = $this->inventory->lockRows($roomType, $locked->stayPeriod());

                if ($wasHolding) {
                    $this->inventory->releaseHeld($rows, $item->rooms);
                } elseif ($wasConfirmed && $locked->status === BookingStatus::CONFIRMED) {
                    $this->inventory->releaseConfirmed($rows, $item->rooms);
                }
            }

            // Compute the refund while the booking still reads as paid.
            $wasPaid = $locked->payment_status->isPaid();
            $breakdown = $wasPaid ? $this->refundBreakdown($locked) : null;
            $refundAmount = (int) ($breakdown['refund_amount'] ?? 0);

            $locked->transitionTo(BookingStatus::CANCELLED);
            $locked->cancelled_at = now();
            $locked->cancellation_reason = $reason;

            // Money already taken must be resolved via a Refund record — never
            // silently marked as refunded here.
            if ($wasPaid) {
                $locked->payment_status = PaymentStatus::REFUND_PENDING;
            }
            $locked->save();

            $request = CancellationRequest::create([
                'booking_id' => $locked->id,
                'requested_by' => $actor?->id,
                'requested_by_type' => $isAdmin ? 'admin' : 'customer',
                'reason' => $reason,
                'eligible_for_refund' => (bool) ($breakdown['eligible'] ?? false),
                'refund_estimate' => $refundAmount,
                'policy_snapshot' => array_merge(
                    (array) $locked->cancellation_policy,
                    $breakdown ? ['refund_breakdown' => $breakdown] : [],
                ),
                'processed_at' => now(),
            ]);

            // Automatically raise the refund so an admin only has to execute the
            // payout (money movement stays manual — DOKU auto-refund isn't used).
            if ($refundAmount > 0) {
                $feeNote = $breakdown['fee_percent'] > 0
                    ? " (biaya pembatalan {$breakdown['fee_percent']}%: ".rupiah($breakdown['fee_amount']).')'
                    : ' (refund penuh, dalam jendela gratis)';

                $this->refunds->request(
                    $locked,
                    $refundAmount,
                    'Otomatis dari pembatalan'.$feeNote,
                    $actor,
                );
            }

            $this->audit->log(AuditAction::CANCELLATION->value, $locked, null, [
                'code' => $locked->code,
                'reason' => $reason,
                'by' => $isAdmin ? 'admin' : 'customer',
                'refund_tier' => $breakdown['tier'] ?? 'unpaid',
                'refund_estimate' => $refundAmount,
                'fee_amount' => $breakdown['fee_amount'] ?? 0,
            ], $actor);

            return $request;
        });
    }
}
