<?php

namespace App\Services\Payments;

use App\Enums\AuditAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingInventoryService;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * "Bayar di tempat" — the guest reserves online and pays cash at the front desk.
 *
 * The two halves are deliberately separate:
 *   reserve()       the guest commits, the room is taken off sale, money is NOT
 *                   yet received (payment_status stays UNPAID);
 *   recordPayment() the front desk records cash actually handed over.
 *
 * Nothing here ever marks a booking paid on the guest's word alone — exactly the
 * rule the DOKU flow follows, for the same reason.
 */
class CashPaymentService
{
    public function __construct(
        private readonly BookingInventoryService $inventory,
        private readonly SettingService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function isEnabled(): bool
    {
        return $this->settings->boolean('cash_payment_enabled', true);
    }

    /**
     * Reserve the room against a promise to pay on arrival.
     */
    public function reserve(Booking $booking): Booking
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Pembayaran di tempat sedang tidak tersedia.');
        }

        if (! $booking->isPayable()) {
            throw new RuntimeException('Pemesanan ini tidak dapat diproses — masa tahan sudah berakhir atau pembayaran sudah diproses.');
        }

        $confirmed = $this->inventory->transactionWithRetry(function () use ($booking) {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            // Re-check under the lock: the sweeper may have expired it between
            // the guest clicking and this transaction opening.
            if (! $locked->isPayable()) {
                throw new RuntimeException('Masa tahan pemesanan sudah berakhir. Silakan buat pemesanan baru.');
            }

            foreach ($locked->items as $item) {
                if ($roomType = $item->roomType) {
                    $rows = $this->inventory->lockRows($roomType, $locked->stayPeriod());
                    $this->inventory->convertHeldToConfirmed($rows, $item->rooms);
                }
            }

            // Choosing a payment method is what HELD -> PENDING_PAYMENT means;
            // the state machine has no HELD -> CONFIRMED edge, and shouldn't.
            if ($locked->status === BookingStatus::HELD) {
                $locked->transitionTo(BookingStatus::PENDING_PAYMENT);
            }

            $locked->transitionTo(BookingStatus::CONFIRMED);
            // Reserved, not paid. The front desk collects on arrival.
            $locked->payment_status = PaymentStatus::UNPAID;
            $locked->confirmed_at = now();
            $locked->held_until = null;
            $locked->save();

            $this->audit->log(AuditAction::BOOKING_CHANGE->value, $locked, null, [
                'code' => $locked->code,
                'action' => 'cash_payment_reserved',
                'amount_due' => $locked->total_amount,
            ]);

            return $locked;
        });

        // A mail failure must never undo a committed reservation.
        try {
            Mail::to($confirmed->customer_email)->queue(new BookingConfirmedMail($confirmed));
        } catch (Throwable $e) {
            Log::error('Cash booking confirmation email failed', [
                'booking' => $confirmed->code,
                'message' => $e->getMessage(),
            ]);
        }

        return $confirmed;
    }

    /**
     * Amount still owed in cash for this booking.
     */
    public function outstanding(Booking $booking): int
    {
        $received = (int) $booking->paymentAttempts()
            ->where('status', PaymentAttemptStatus::PAID->value)
            ->sum('amount');

        return max(0, (int) $booking->total_amount - $received);
    }

    /**
     * Record cash actually received at the desk.
     */
    public function recordPayment(Booking $booking, User $admin, int $amount, ?string $notes = null): PaymentAttempt
    {
        if (! in_array($booking->status, [BookingStatus::CONFIRMED, BookingStatus::CHECKED_IN], true)) {
            throw new RuntimeException('Pembayaran tunai hanya dapat dicatat untuk pemesanan terkonfirmasi atau tamu yang sedang menginap.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Jumlah pembayaran harus lebih dari nol.');
        }

        $outstanding = $this->outstanding($booking);

        if ($outstanding <= 0) {
            throw new RuntimeException('Pemesanan ini sudah lunas.');
        }

        if ($amount > $outstanding) {
            throw new RuntimeException('Jumlah melebihi sisa tagihan ('.rupiah($outstanding).').');
        }

        return $this->inventory->transactionWithRetry(function () use ($booking, $admin, $amount, $notes, $outstanding) {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            $attempt = PaymentAttempt::create([
                'booking_id' => $locked->id,
                'provider' => 'cash',
                'invoice_number' => 'CASH-'.$locked->code.'-'.Str::upper(Str::random(6)),
                'request_id' => (string) Str::uuid(),
                'amount' => $amount,
                'currency' => $locked->currency,
                'status' => PaymentAttemptStatus::PAID,
                'paid_at' => now(),
            ]);

            // Partial cash (deposit now, balance later) keeps the booking PENDING.
            $locked->payment_status = $amount >= $outstanding
                ? PaymentStatus::PAID
                : PaymentStatus::PENDING;
            $locked->save();

            $this->audit->log(AuditAction::PAYMENT_CONFIRMED->value, $locked, null, array_filter([
                'code' => $locked->code,
                'method' => 'cash',
                'amount' => $amount,
                'outstanding_after' => $outstanding - $amount,
                'notes' => $notes,
            ]), $admin);

            return $attempt;
        });
    }
}
