<?php

namespace App\Services\Operations;

use App\Enums\AuditAction;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Refund recording (§22).
 *
 * A refund is never "done" until an admin marks it SUCCEEDED. Automatic DOKU
 * refunds are not available here, so refunds are recorded manually and clearly
 * labelled as such. The total refunded can never exceed the amount actually paid.
 */
class RefundService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Amount actually paid for a booking (sum of PAID attempts).
     */
    public function paidAmount(Booking $booking): int
    {
        return (int) $booking->paymentAttempts()
            ->where('status', PaymentAttemptStatus::PAID->value)
            ->sum('amount');
    }

    /**
     * Already-succeeded refunds.
     */
    public function refundedAmount(Booking $booking): int
    {
        return (int) $booking->refunds()
            ->where('status', RefundStatus::SUCCEEDED->value)
            ->sum('amount');
    }

    public function refundableRemaining(Booking $booking): int
    {
        return max(0, $this->paidAmount($booking) - $this->refundedAmount($booking));
    }

    public function request(Booking $booking, int $amount, string $reason, ?User $actor = null): Refund
    {
        if (! $booking->payment_status->isRefundable() && $booking->payment_status !== PaymentStatus::REFUND_PENDING) {
            throw new RuntimeException('Pemesanan yang belum dibayar tidak dapat direfund.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Jumlah refund harus lebih dari nol.');
        }

        $remaining = $this->refundableRemaining($booking);
        if ($amount > $remaining) {
            throw new RuntimeException(
                'Jumlah refund melebihi sisa yang dapat direfund ('.rupiah($remaining).').'
            );
        }

        $refund = Refund::create([
            'booking_id' => $booking->id,
            'payment_attempt_id' => $booking->paymentAttempts()
                ->where('status', PaymentAttemptStatus::PAID->value)
                ->value('id'),
            'amount' => $amount,
            'status' => RefundStatus::REQUESTED,
            'reason' => $reason,
            'is_manual' => true,
            'requested_at' => now(),
        ]);

        $this->audit->log(AuditAction::REFUND->value, $refund, null, [
            'booking' => $booking->code,
            'amount' => $amount,
            'status' => RefundStatus::REQUESTED->value,
        ], $actor);

        return $refund;
    }

    /**
     * Move a refund through its lifecycle. Only SUCCEEDED reduces the paid
     * amount and updates the booking's payment status.
     */
    public function updateStatus(Refund $refund, RefundStatus $status, ?User $actor = null, ?string $notes = null, ?string $reference = null): Refund
    {
        return DB::transaction(function () use ($refund, $status, $actor, $notes, $reference) {
            /** @var Refund $locked */
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->first();

            if ($locked->status === RefundStatus::SUCCEEDED) {
                throw new RuntimeException('Refund ini sudah selesai dan tidak dapat diubah.');
            }

            $booking = $locked->booking;

            // Re-check the cap at completion time — another refund may have
            // succeeded since this one was requested.
            if ($status === RefundStatus::SUCCEEDED) {
                $otherRefunded = (int) $booking->refunds()
                    ->where('status', RefundStatus::SUCCEEDED->value)
                    ->whereKeyNot($locked->id)
                    ->sum('amount');

                if ($otherRefunded + $locked->amount > $this->paidAmount($booking)) {
                    throw new RuntimeException('Total refund akan melebihi jumlah yang dibayarkan.');
                }
            }

            $old = $locked->status->value;
            $locked->status = $status;
            $locked->notes = $notes ?? $locked->notes;
            $locked->provider_reference = $reference ?? $locked->provider_reference;
            $locked->processed_by = $actor?->id;
            $locked->processed_at = in_array($status, [RefundStatus::SUCCEEDED, RefundStatus::FAILED, RefundStatus::REJECTED], true)
                ? now()
                : null;
            $locked->save();

            if ($status === RefundStatus::SUCCEEDED) {
                $this->syncBookingPaymentStatus($booking);
            }

            $this->audit->log(AuditAction::REFUND->value, $locked, ['status' => $old], [
                'status' => $status->value,
                'amount' => $locked->amount,
                'booking' => $booking->code,
            ], $actor);

            return $locked;
        });
    }

    /**
     * Reflect completed refunds on the booking: full vs partial.
     */
    private function syncBookingPaymentStatus(Booking $booking): void
    {
        $paid = $this->paidAmount($booking);
        $refunded = $this->refundedAmount($booking);

        $booking->payment_status = match (true) {
            $paid > 0 && $refunded >= $paid => PaymentStatus::REFUNDED,
            $refunded > 0 => PaymentStatus::PARTIALLY_REFUNDED,
            default => $booking->payment_status,
        };
        $booking->save();
    }
}
