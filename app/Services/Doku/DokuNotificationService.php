<?php

namespace App\Services\Doku;

use App\Enums\AuditAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingInventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Processes a verified DOKU notification.
 *
 * Guarantees:
 *  - a duplicate notification never confirms twice, never changes inventory
 *    twice and never sends a second confirmation email (unique payment_events);
 *  - an invalid signature changes nothing and is logged as a security event;
 *  - an amount mismatch never confirms — it routes to PAYMENT_REVIEW;
 *  - a late payment re-checks inventory under lock before confirming.
 */
class DokuNotificationService
{
    public function __construct(
        private readonly DokuPaymentMapper $mapper,
        private readonly DokuPayloadRedactor $redactor,
        private readonly BookingInventoryService $inventory,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return string one of: processed, duplicate, invalid_signature, unknown_invoice,
     *                amount_mismatch, review, ignored
     */
    public function handle(array $payload, string $rawBody, bool $signatureValid, ?string $requestId): string
    {
        $payloadHash = hash('sha256', $rawBody);
        $invoiceNumber = $this->mapper->invoiceNumber($payload);
        $attempt = $invoiceNumber
            ? PaymentAttempt::query()->where('invoice_number', $invoiceNumber)->first()
            : null;

        // --- Idempotency gate -------------------------------------------------
        // UNIQUE(provider, provider_request_id) only bites on non-NULL values —
        // MySQL treats NULLs as distinct — so a notification that arrives without
        // a Request-Id falls back to its payload hash as the key. Without this a
        // request-id-less replay would insert a fresh row every time.
        try {
            $event = PaymentEvent::create([
                'provider' => 'doku',
                'payment_attempt_id' => $attempt?->id,
                'provider_request_id' => $requestId ?: 'payload:'.substr($payloadHash, 0, 40),
                'event_type' => $this->mapper->eventType($payload),
                'payload_hash' => $payloadHash,
                'signature_valid' => $signatureValid,
                'processing_status' => 'received',
                'payload_redacted' => $this->redactor->redact($payload),
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Unique violation => we have already seen this notification.
            if ($this->isUniqueViolation($e)) {
                return 'duplicate';
            }

            throw $e;
        }

        // --- Signature ---------------------------------------------------------
        if (! $signatureValid) {
            $this->finish($event, 'failed', 'Invalid signature — notification rejected.');
            $this->audit->log(AuditAction::PAYMENT_SIGNATURE_INVALID->value, null, null, [
                'invoice_number' => $invoiceNumber,
                'request_id' => $requestId,
            ]);
            Log::warning('DOKU notification with invalid signature rejected', [
                'invoice_number' => $invoiceNumber,
                'request_id' => $requestId,
            ]);

            return 'invalid_signature';
        }

        if (! $attempt) {
            $this->finish($event, 'ignored', 'Unknown invoice number: '.($invoiceNumber ?? 'null'));

            return 'unknown_invoice';
        }

        $booking = $attempt->booking;
        if (! $booking) {
            $this->finish($event, 'ignored', 'Payment attempt has no booking.');

            return 'ignored';
        }

        // --- Amount / currency verification ------------------------------------
        $amount = $this->mapper->amount($payload);
        $currency = $this->mapper->currency($payload);
        $status = $this->mapper->status($payload);

        if ($status === 'paid') {
            if ($amount === null || $amount !== (int) $attempt->amount || $amount !== (int) $booking->total_amount) {
                $this->flagForReview($booking, $attempt, "Amount mismatch: notified {$amount}, expected {$booking->total_amount}");
                $this->finish($event, 'processed', 'Amount mismatch routed to payment review.');

                return 'amount_mismatch';
            }

            if (strtoupper($currency) !== strtoupper($booking->currency)) {
                $this->flagForReview($booking, $attempt, "Currency mismatch: notified {$currency}, expected {$booking->currency}");
                $this->finish($event, 'processed', 'Currency mismatch routed to payment review.');

                return 'amount_mismatch';
            }
        }

        // --- Apply the status ---------------------------------------------------
        $result = match ($status) {
            'paid' => $this->confirmPayment($booking, $attempt, $payload),
            'failed' => $this->markFailed($booking, $attempt, $payload),
            'expired' => $this->markExpired($attempt),
            'pending' => 'ignored',
            default => $this->flagForReview($booking, $attempt, 'Unrecognised provider status: '.$this->mapper->rawStatus($payload)),
        };

        $this->finish($event, 'processed', null);

        return $result;
    }

    /**
     * Confirm the booking, moving inventory from held to confirmed. Handles the
     * late-payment case where the hold already expired.
     */
    private function confirmPayment(Booking $booking, PaymentAttempt $attempt, array $payload): string
    {
        $outcome = $this->inventory->transactionWithRetry(function () use ($booking, $attempt) {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            // Already confirmed by an earlier notification — nothing to redo.
            if (in_array($locked->status, [BookingStatus::CONFIRMED, BookingStatus::CHECKED_IN, BookingStatus::CHECKED_OUT], true)) {
                return 'already_confirmed';
            }

            $wasHeld = in_array($locked->status, [BookingStatus::HELD, BookingStatus::PENDING_PAYMENT], true);
            $lateRecovery = false;

            foreach ($locked->items as $item) {
                $roomType = $item->roomType;
                if (! $roomType) {
                    continue;
                }

                $rows = $this->inventory->lockRows($roomType, $locked->stayPeriod());

                if ($wasHeld) {
                    // Normal path: the hold is still ours, just settle it.
                    $this->inventory->convertHeldToConfirmed($rows, $item->rooms);
                } else {
                    // Late payment: the hold lapsed and inventory was released.
                    // Re-check availability before taking it back.
                    if (! $this->inventory->hasCapacity($rows, $locked->stayPeriod(), $item->rooms, $roomType->sellableRoomCount())) {
                        return 'no_inventory';
                    }
                    $this->inventory->increaseConfirmed($rows, $item->rooms);
                    $lateRecovery = true;
                }
            }

            $locked->transitionTo(BookingStatus::CONFIRMED);
            $locked->payment_status = PaymentStatus::PAID;
            $locked->confirmed_at = now();
            $locked->late_payment_recovery = $lateRecovery;
            $locked->save();

            $attempt->update([
                'status' => PaymentAttemptStatus::PAID,
                'paid_at' => now(),
            ]);

            return $lateRecovery ? 'confirmed_late' : 'confirmed';
        });

        if ($outcome === 'already_confirmed') {
            return 'processed';
        }

        if ($outcome === 'no_inventory') {
            // Paid but nothing left to give — needs human handling / refund.
            $this->flagForReview($booking, $attempt, 'Late payment received but no inventory remains.');

            return 'review';
        }

        $booking->refresh();

        $this->audit->log(AuditAction::PAYMENT_CONFIRMED->value, $booking, null, [
            'code' => $booking->code,
            'amount' => $attempt->amount,
            'late_payment_recovery' => $outcome === 'confirmed_late',
        ]);

        // Queued so a slow mail server can never delay the webhook response.
        Mail::to($booking->customer_email)->queue(new BookingConfirmedMail($booking));

        return 'processed';
    }

    private function markFailed(Booking $booking, PaymentAttempt $attempt, array $payload): string
    {
        $attempt->update([
            'status' => PaymentAttemptStatus::FAILED,
            'failure_code' => $this->mapper->rawStatus($payload),
            'failure_message' => 'Pembayaran gagal diproses oleh penyedia pembayaran.',
        ]);

        // The booking stays alive while the hold is still valid so the guest can retry.
        if ($booking->isPayable()) {
            $booking->payment_status = PaymentStatus::PENDING;
        } else {
            $booking->payment_status = PaymentStatus::FAILED;
        }
        $booking->save();

        return 'processed';
    }

    private function markExpired(PaymentAttempt $attempt): string
    {
        $attempt->update(['status' => PaymentAttemptStatus::EXPIRED]);

        return 'processed';
    }

    /**
     * Park the booking for admin review — never auto-confirm in this state.
     */
    private function flagForReview(Booking $booking, PaymentAttempt $attempt, string $reason): string
    {
        DB::transaction(function () use ($booking, $reason) {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if ($locked->status->canTransitionTo(BookingStatus::PAYMENT_REVIEW)) {
                $locked->transitionTo(BookingStatus::PAYMENT_REVIEW);
            }
            $locked->payment_status = PaymentStatus::REVIEW;
            $locked->save();

            $this->audit->log(AuditAction::PAYMENT_REVIEW->value, $locked, null, ['reason' => $reason]);
        });

        Log::warning('DOKU payment routed to review', ['booking' => $booking->code, 'reason' => $reason]);

        return 'review';
    }

    private function finish(PaymentEvent $event, string $status, ?string $error): void
    {
        $event->update([
            'processing_status' => $status,
            'processed_at' => now(),
            'error_message' => $error,
        ]);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // MySQL/MariaDB duplicate entry
        // PostgreSQL reports SQLSTATE 23505 / "duplicate key value".
        $message = strtolower($e->getMessage());

        return (string) ($e->errorInfo[1] ?? '') === '1062'
            || (string) ($e->errorInfo[0] ?? '') === '23505'
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'duplicate key value');
    }
}
