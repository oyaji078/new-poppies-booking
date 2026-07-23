<?php

namespace App\Console\Commands;

use App\Models\PaymentAttempt;
use App\Services\Doku\DokuNotificationVerifier;
use App\Services\Doku\DokuSignatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Posts a correctly-signed DOKU notification at our own webhook.
 *
 * This is the local stand-in for "DOKU calls us back": it exercises the real
 * route, the real signature verification and the real confirmation path without
 * needing the app to be reachable from the internet. It is a TEST tool — it
 * makes the system believe a payment happened, so it refuses to run against a
 * production DOKU configuration without an explicit confirmation.
 *
 *   php artisan doku:simulate-notification NPS-20260723-ABC123-1
 *   php artisan doku:simulate-notification NPS-...-1 --status=FAILED
 */
class DokuSimulateNotification extends Command
{
    protected $signature = 'doku:simulate-notification
        {invoice : Nomor invoice pada payment_attempts}
        {--status=SUCCESS : transaction.status DOKU (SUCCESS, PENDING, FAILED, EXPIRED, ...)}
        {--amount= : Timpa order.amount untuk menguji jalur amount mismatch}
        {--request-id= : Pakai ulang Request-Id untuk menguji penolakan duplikat}
        {--url= : Timpa URL notifikasi (mis. URL ngrok)}
        {--invalid-signature : Kirim signature palsu untuk menguji penolakan 401}';

    protected $description = 'Send a signed DOKU notification to this application (local end-to-end payment test)';

    public function handle(DokuSignatureService $signatures, DokuNotificationVerifier $verifier): int
    {
        if ((string) config('doku.environment') === 'production'
            && ! $this->confirm('DOKU_ENVIRONMENT=production. Perintah ini memalsukan notifikasi pembayaran. Lanjutkan?', false)) {
            $this->components->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        if ((string) config('doku.secret_key') === '') {
            $this->components->error('DOKU_SECRET_KEY kosong — notifikasi tidak dapat ditandatangani.');

            return self::FAILURE;
        }

        $invoice = (string) $this->argument('invoice');
        $attempt = PaymentAttempt::query()->where('invoice_number', $invoice)->first();

        if (! $attempt) {
            $this->components->error("Invoice \"{$invoice}\" tidak ditemukan di payment_attempts.");
            $this->line('  Invoice terakhir:');
            PaymentAttempt::query()->latest('id')->take(5)->get()
                ->each(fn ($a) => $this->line("  • {$a->invoice_number} — Rp ".number_format($a->amount, 0, ',', '.')." — {$a->status->value}"));

            return self::FAILURE;
        }

        $amount = $this->option('amount') !== null ? (int) $this->option('amount') : (int) $attempt->amount;
        $status = strtoupper((string) $this->option('status'));

        $payload = [
            'service' => ['id' => 'VIRTUAL_ACCOUNT'],
            'acquirer' => ['id' => 'BCA'],
            'channel' => ['id' => 'VIRTUAL_ACCOUNT_BCA'],
            'order' => [
                'invoice_number' => $invoice,
                'amount' => $amount,
                'currency' => $attempt->currency ?: 'IDR',
            ],
            'transaction' => [
                'status' => $status,
                'date' => now()->toIso8601String(),
            ],
        ];

        // The digest must cover the exact bytes we transmit, so the body is
        // encoded once and reused for both the signature and the request.
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $requestId = (string) ($this->option('request-id') ?: Str::uuid());
        $timestamp = $signatures->timestamp();
        $target = $verifier->notificationTarget();

        $signature = $this->option('invalid-signature')
            ? 'HMACSHA256=signature-yang-sengaja-salah'
            : $signatures->sign($requestId, $timestamp, $target, $signatures->digest($raw));

        $url = (string) ($this->option('url') ?: config('doku.notification_url'));

        $this->components->info('Mengirim notifikasi DOKU tiruan');
        $this->table(['Kunci', 'Nilai'], [
            ['URL', $url],
            ['Request-Target', $target],
            ['Request-Id', $requestId],
            ['Invoice', $invoice],
            ['Jumlah', 'Rp '.number_format($amount, 0, ',', '.')],
            ['Status', $status],
            ['Signature', $this->option('invalid-signature') ? 'SENGAJA SALAH' : 'valid'],
        ]);

        try {
            $response = Http::withHeaders([
                'Client-Id' => (string) config('doku.client_id'),
                'Request-Id' => $requestId,
                'Request-Timestamp' => $timestamp,
                'Signature' => $signature,
                'Content-Type' => 'application/json',
            ])->timeout(30)->withBody($raw, 'application/json')->post($url);
        } catch (\Throwable $e) {
            $this->components->error('Tidak dapat menghubungi '.$url.': '.$e->getMessage());
            $this->line('  Pastikan <comment>php artisan serve</comment> sedang berjalan dan APP_URL sesuai.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('  HTTP '.$response->status().' → '.$response->body());
        $this->newLine();

        $booking = $attempt->fresh()->booking;
        $this->table(['Setelah notifikasi', 'Nilai'], [
            ['Booking', $booking?->code ?? '-'],
            ['Status pemesanan', $booking?->status->value ?? '-'],
            ['Status pembayaran', $booking?->payment_status->value ?? '-'],
            ['Status attempt', $attempt->fresh()->status->value],
        ]);

        return $response->successful() || $response->status() === 401 ? self::SUCCESS : self::FAILURE;
    }
}
