<?php

namespace App\Services\Doku;

use App\Enums\BookingStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates a DOKU Checkout payment for a booking and records the attempt.
 *
 * The amount sent to DOKU is ALWAYS the server-side booking total; nothing from
 * the browser is trusted. Retrying payment reuses the same booking and never
 * reserves inventory twice.
 */
class DokuCheckoutService
{
    public function __construct(
        private readonly DokuSignatureService $signatures,
        private readonly DokuPayloadRedactor $redactor,
        private readonly PaymentCallbackToken $callbackToken,
    ) {}

    /**
     * Return the existing usable attempt, or create a new one at DOKU.
     */
    public function startPayment(Booking $booking): PaymentAttempt
    {
        if (! $booking->isPayable()) {
            throw $booking->isHoldExpired()
                ? BookingException::holdExpired()
                : BookingException::invalidStay('Pemesanan ini tidak dapat dibayar.');
        }

        // Reuse a still-valid attempt so refreshing the page doesn't spam DOKU.
        $active = $booking->activePaymentAttempt();
        if ($active && ! $active->isExpired() && $active->payment_url && $active->amount === $booking->total_amount) {
            return $active;
        }

        // Supersede any stale pending attempt — only one may be active.
        $booking->paymentAttempts()
            ->where('status', PaymentAttemptStatus::PENDING->value)
            ->update(['status' => PaymentAttemptStatus::CANCELLED->value]);

        return $this->createAttempt($booking);
    }

    private function createAttempt(Booking $booking): PaymentAttempt
    {
        $this->assertTotalIntegrity($booking);

        $amount = (int) $booking->total_amount;
        $invoiceNumber = $this->generateInvoiceNumber($booking);
        $requestId = (string) Str::uuid();
        $timestamp = $this->signatures->timestamp();
        $target = (string) config('doku.checkout_path');
        $dueMinutes = $this->paymentDueMinutes($booking);

        $body = $this->buildRequestBody($booking, $invoiceNumber, $amount, $dueMinutes);

        // Digest MUST be computed over the exact bytes we transmit.
        $rawBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $digest = $this->signatures->digest($rawBody);
        $signature = $this->signatures->sign($requestId, $timestamp, $target, $digest);

        $headers = [
            'Client-Id' => (string) config('doku.client_id'),
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => $signature,
            'Content-Type' => 'application/json',
        ];

        $url = config('doku.base_url').$target;

        $response = Http::withHeaders($headers)
            ->timeout((int) config('doku.timeout', 30))
            ->withBody($rawBody, 'application/json')
            ->post($url);

        $json = $response->json() ?? [];

        if (! $response->successful()) {
            $reason = $this->failureReason($json);

            Log::warning('DOKU checkout creation failed', [
                'booking' => $booking->code,
                'status' => $response->status(),
                'error_code' => data_get($json, 'error.code'),
                'errors' => $reason,
            ]);

            throw new RuntimeException('Gagal membuat halaman pembayaran: '.$reason);
        }

        $paymentUrl = data_get($json, 'response.payment.url');
        if (! $paymentUrl) {
            throw new RuntimeException('Layanan pembayaran tidak mengembalikan URL pembayaran.');
        }

        return DB::transaction(function () use ($booking, $invoiceNumber, $requestId, $amount, $paymentUrl, $dueMinutes, $body, $json) {
            $attempt = PaymentAttempt::create([
                'booking_id' => $booking->id,
                'provider' => 'doku',
                'invoice_number' => $invoiceNumber,
                'request_id' => $requestId,
                'amount' => $amount,
                'currency' => $booking->currency,
                'payment_url' => $paymentUrl,
                'status' => PaymentAttemptStatus::PENDING,
                // Never let the payment window outlive the inventory hold.
                'expires_at' => $booking->held_until
                    ? min(now()->addMinutes($dueMinutes), $booking->held_until)
                    : now()->addMinutes($dueMinutes),
                'request_payload_redacted' => $this->redactor->redact($body),
                'response_payload_redacted' => $this->redactor->redact($json),
            ]);

            // HELD -> PENDING_PAYMENT once a payment page exists.
            if ($booking->status === BookingStatus::HELD) {
                $booking->transitionTo(BookingStatus::PENDING_PAYMENT);
                $booking->payment_status = PaymentStatus::PENDING;
                $booking->save();
            }

            return $attempt;
        });
    }

    /**
     * DOKU reports failures in three different shapes:
     *
     *   {"error":{"code":"invalid_client_id","message":"..."}}   auth/routing errors
     *   {"message":["Invalid character, allowed only ..."]}      validation errors
     *   {"error_messages":["..."]}                               legacy
     *
     * Reading only one of them silently swallows the real cause and leaves
     * "layanan pembayaran tidak merespons" in the log — exactly the message that
     * is no help at 2am.
     *
     * @param  array<string, mixed>  $json
     */
    private function failureReason(array $json): string
    {
        $messages = array_filter([
            (string) data_get($json, 'error.message', ''),
            ...array_map('strval', (array) data_get($json, 'message', [])),
            ...array_map('strval', (array) data_get($json, 'error_messages', [])),
        ]);

        if ($messages === []) {
            return 'layanan pembayaran tidak merespons';
        }

        $code = (string) data_get($json, 'error.code', '');

        return implode(', ', $messages).($code !== '' ? " ({$code})" : '');
    }

    /**
     * How long the DOKU payment page may stay open, in minutes.
     *
     * The page must never outlive the inventory hold: once the hold lapses the
     * rooms go back on sale, so a still-payable page would invite a late payment
     * for rooms we no longer have. Clamped to whatever is left of the hold.
     */
    private function paymentDueMinutes(Booking $booking): int
    {
        $configured = (int) config('doku.payment_due_minutes', 30);

        if (! $booking->held_until) {
            return max(1, $configured);
        }

        $remaining = intdiv(
            max(0, $booking->held_until->getTimestamp() - now()->getTimestamp()),
            60
        );

        return max(1, min($configured, $remaining));
    }

    /**
     * The nightly snapshots must still reconcile to the stored total; a mismatch
     * means the booking was tampered with and payment must not proceed.
     */
    private function assertTotalIntegrity(Booking $booking): void
    {
        $snapshotTotal = 0;
        foreach ($booking->items as $item) {
            $snapshotTotal += (int) $item->nights()->sum('final_amount');
        }

        if ($snapshotTotal !== (int) $booking->total_amount) {
            Log::critical('Booking total does not reconcile with nightly snapshots', [
                'booking' => $booking->code,
                'stored_total' => $booking->total_amount,
                'snapshot_total' => $snapshotTotal,
            ]);

            throw new RuntimeException('Total pemesanan tidak konsisten. Silakan hubungi kami.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestBody(Booking $booking, string $invoiceNumber, int $amount, int $dueMinutes): array
    {
        $callback = (string) config('doku.callback_url');

        return [
            'order' => array_filter([
                'invoice_number' => $invoiceNumber,
                'amount' => $amount,
                'currency' => $booking->currency,
                // The token lets the returning browser prove it is this booking's
                // payer even when the session cookie did not survive the redirect
                // back from DOKU (e.g. a different host).
                'callback_url' => $callback !== ''
                    ? $callback.'?code='.$booking->code.'&t='.$this->callbackToken->for($booking)
                    : null,
                'language' => 'ID',
                'auto_redirect' => true,
                'line_items' => $this->buildLineItems($booking, $amount),
            ], fn ($v) => $v !== null),

            'payment' => array_filter([
                'payment_due_date' => $dueMinutes,
                // Which methods appear on the DOKU page. Empty => DOKU shows every
                // method enabled on the account. A single method sends the guest
                // straight to it (no chooser) — that is how "QRIS only" works.
                'payment_method_types' => $this->paymentMethodTypes(),
            ], fn ($v) => $v !== null && $v !== []),

            'customer' => array_filter([
                'id' => 'CUST-'.$booking->id,
                'name' => $this->sanitiseText($booking->customer_name, 128),
                'email' => $booking->customer_email,
                'phone' => $booking->customer_phone,
                'country' => 'ID',
            ], fn ($v) => $v !== null && $v !== ''),

            ...$this->overrideNotificationUrl(),
        ];
    }

    /**
     * Tell DOKU where to send the notification for THIS transaction.
     *
     * DOKU allows overriding the dashboard-registered Notification URL per
     * request as long as the path is identical — only the host may differ. That
     * is what makes a tunnel workable: the ngrok domain changes on every restart,
     * but the dashboard registration can stay untouched.
     *
     * Skipped when the configured URL is not reachable from the internet, since
     * DOKU rejects such an override and would fail the whole checkout.
     *
     * @return array<string, mixed>
     */
    private function overrideNotificationUrl(): array
    {
        $url = (string) config('doku.notification_url');

        if ($url === '') {
            return [];
        }

        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return [];
        }

        return ['additional_info' => ['override_notification_url' => $url]];
    }

    /**
     * The DOKU payment methods to offer, from config('doku.payment_method_types').
     *
     * Empty means "let DOKU show whatever the account has enabled". Values are
     * validated against DOKU's documented identifiers so a typo in .env cannot
     * silently disable payments — an unknown method is dropped with a warning
     * rather than sent and rejected.
     *
     * @return array<int, string>
     */
    private function paymentMethodTypes(): array
    {
        $configured = config('doku.payment_method_types', []);

        $configured = is_array($configured)
            ? $configured
            : array_filter(array_map('trim', explode(',', (string) $configured)));

        $known = (array) config('doku.known_payment_method_types', []);
        $types = [];

        foreach ($configured as $type) {
            $type = strtoupper(trim((string) $type));
            if ($type === '') {
                continue;
            }
            if ($known !== [] && ! in_array($type, $known, true)) {
                Log::warning('Unknown DOKU payment method type ignored', ['type' => $type]);

                continue;
            }
            $types[] = $type;
        }

        return array_values(array_unique($types));
    }

    /**
     * Coerce human-entered text into the only characters DOKU accepts:
     *
     *   a-z A-Z 0-9 . - / + , = _ : ' @ % ( ) and space
     *
     * Guest names and room type names are typed by people, so anything else is
     * reachable in normal use — an accented name, a typographic dash pasted from
     * Word, an emoji. DOKU rejects the whole checkout with a message that names
     * no field, so the payment would fail for reasons nobody could diagnose.
     * Accents are transliterated rather than dropped so "José" stays "Jose".
     */
    private function sanitiseText(?string $value, int $maxLength): string
    {
        $ascii = Str::ascii((string) $value);
        $clean = preg_replace('/[^a-zA-Z0-9 .\-\/+,=_:\'@%()]/', ' ', $ascii) ?? '';
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');

        return Str::limit($clean, $maxLength, '');
    }

    /**
     * Line items whose prices add up EXACTLY to order.amount.
     *
     * The booking total is subtotal − discount + tax + service, so per-room
     * subtotals alone never reach it. DOKU rejects (and paylater channels always
     * reject) a basket that does not reconcile with the amount, so the total is
     * allocated across the items proportionally, with the rounding remainder
     * absorbed by the last line. Quantity stays 1 and the room count lives in the
     * label — that keeps price × quantity exact for any number of rooms.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildLineItems(Booking $booking, int $amount): array
    {
        $items = $booking->items->values();

        if ($items->isEmpty()) {
            return [[
                'name' => 'Pemesanan '.$booking->code,
                'quantity' => 1,
                'price' => $amount,
            ]];
        }

        $subtotalSum = (int) $items->sum('subtotal_amount');
        $lines = [];
        $allocated = 0;
        $last = $items->count() - 1;

        foreach ($items as $index => $item) {
            $share = $index === $last
                ? $amount - $allocated              // absorbs every rounding remainder
                : ($subtotalSum > 0
                    ? (int) floor($amount * (int) $item->subtotal_amount / $subtotalSum)
                    : intdiv($amount, $items->count()));

            $allocated += $share;

            $lines[] = [
                'name' => $this->sanitiseText(
                    $item->room_type_name.' ('.(int) $item->rooms.' kamar, '.$booking->nights.' malam)',
                    128,
                ),
                'quantity' => 1,
                'price' => $share,
            ];
        }

        return $lines;
    }

    /**
     * Each attempt gets a fresh, unique invoice number derived from the booking
     * code plus the attempt sequence.
     */
    private function generateInvoiceNumber(Booking $booking): string
    {
        $sequence = $booking->paymentAttempts()->count() + 1;

        do {
            $candidate = $booking->code.'-'.$sequence;
            $exists = PaymentAttempt::query()->where('invoice_number', $candidate)->exists();
            $sequence++;
        } while ($exists);

        return $candidate;
    }
}
