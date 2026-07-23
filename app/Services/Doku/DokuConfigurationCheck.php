<?php

namespace App\Services\Doku;

use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The single source of truth for "is the DOKU configuration sound?".
 *
 * Shared by `php artisan doku:check` and the super-admin switcher screen so the
 * two can never disagree about what counts as broken. Every rule here is a
 * failure mode that otherwise only shows up as a payment that silently never
 * completes.
 */
class DokuConfigurationCheck
{
    /** @var array<int, string> */
    private array $problems = [];

    /** @var array<int, string> */
    private array $warnings = [];

    private bool $credentialsUsable = true;

    public function __construct(private readonly SettingService $settings) {}

    /**
     * @return array{
     *     problems: array<int, string>,
     *     warnings: array<int, string>,
     *     credentials_usable: bool,
     *     passes: bool,
     *     summary: array<string, string>
     * }
     */
    public function run(): array
    {
        $this->problems = [];
        $this->warnings = [];
        $this->credentialsUsable = true;

        $environment = (string) config('doku.environment');
        $clientId = (string) config('doku.client_id');
        $secretKey = (string) config('doku.secret_key');
        $baseUrl = (string) config('doku.base_url');
        $notifyUrl = (string) config('doku.notification_url');
        $callbackUrl = (string) config('doku.callback_url');
        $dueMinutes = (int) config('doku.payment_due_minutes', 30);
        $holdMinutes = $this->settings->integer('booking_hold_minutes', 30);

        $this->checkCredentials($clientId, $secretKey);
        $this->checkBaseUrl($environment, $baseUrl);
        $this->checkNotificationUrl($notifyUrl);
        $this->checkCallbackUrl($callbackUrl);
        $this->checkPaymentWindow($dueMinutes, $holdMinutes);
        $this->checkProductionReadiness($environment);

        return [
            'problems' => $this->problems,
            'warnings' => $this->warnings,
            'credentials_usable' => $this->credentialsUsable,
            'passes' => $this->problems === [],
            'summary' => [
                'DOKU_ENVIRONMENT' => $environment,
                'DOKU_CLIENT_ID' => self::mask($clientId),
                'DOKU_SECRET_KEY' => self::mask($secretKey),
                'DOKU_BASE_URL' => $baseUrl,
                'Endpoint checkout' => $baseUrl.config('doku.checkout_path'),
                'DOKU_NOTIFICATION_URL' => $notifyUrl ?: '(kosong)',
                'DOKU_CALLBACK_URL' => $callbackUrl ?: '(kosong)',
                'DOKU_PAYMENT_DUE_MINUTES' => (string) $dueMinutes,
                'booking_hold_minutes (DB)' => (string) $holdMinutes,
                'APP_URL' => (string) config('app.url'),
            ],
        ];
    }

    public static function mask(string $value): string
    {
        if ($value === '') {
            return '(kosong)';
        }

        return strlen($value) <= 8
            ? str_repeat('*', strlen($value))
            : substr($value, 0, 4).str_repeat('*', 8).substr($value, -4);
    }

    private function checkCredentials(string $clientId, string $secretKey): void
    {
        if ($clientId === '') {
            $this->problems[] = 'DOKU_CLIENT_ID kosong — ambil dari halaman API Keys dashboard DOKU (Settings → API Keys).';
            $this->credentialsUsable = false;
        } elseif (! Str::startsWith($clientId, ['BRN-', 'MCH-'])) {
            $this->warnings[] = "DOKU_CLIENT_ID \"{$clientId}\" tidak berawalan BRN-/MCH-; pastikan ini Client ID, bukan Merchant ID.";
        }

        if ($secretKey === '') {
            $this->problems[] = 'DOKU_SECRET_KEY kosong — tanpa ini signature tidak dapat dibuat maupun diverifikasi.';
            $this->credentialsUsable = false;
        } elseif (strlen($secretKey) < 20) {
            $this->warnings[] = 'DOKU_SECRET_KEY tampak terlalu pendek; pastikan disalin utuh.';
        }
    }

    private function checkBaseUrl(string $environment, string $baseUrl): void
    {
        $isSandboxHost = str_contains($baseUrl, 'sandbox');

        if ($environment === 'production' && $isSandboxHost) {
            $this->problems[] = 'Mode produksi aktif tetapi DOKU_BASE_URL masih menunjuk sandbox.';
        }

        if ($environment !== 'production' && ! $isSandboxHost) {
            $this->problems[] = "Mode {$environment} aktif tetapi DOKU_BASE_URL menunjuk host produksi — kredensial sandbox akan ditolak.";
        }

        if (! Str::startsWith($baseUrl, 'https://')) {
            $this->problems[] = 'DOKU_BASE_URL wajib https.';
            $this->credentialsUsable = false;
        }
    }

    private function checkNotificationUrl(string $url): void
    {
        if ($url === '') {
            $this->problems[] = 'DOKU_NOTIFICATION_URL kosong — signature notifikasi dihitung dari path URL ini.';

            return;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $host = (string) parse_url($url, PHP_URL_HOST);

        // The signature over an inbound notification uses this path as
        // Request-Target, so a path that is not our route can never verify.
        $expected = '/webhook/doku/notifications';
        if ($path !== $expected) {
            $this->problems[] = "Path DOKU_NOTIFICATION_URL adalah \"{$path}\", seharusnya \"{$expected}\" — signature notifikasi tidak akan pernah cocok.";
        }

        if (! Route::has('payments.doku.notifications')) {
            $this->problems[] = 'Route payments.doku.notifications tidak terdaftar.';
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || $host === '') {
            $this->problems[] = "DOKU tidak dapat menjangkau \"{$url}\". Ekspos aplikasi (mis. ngrok http 8000), lalu set APP_URL ke URL publik tersebut.";
        } elseif (! Str::startsWith($url, 'https://')) {
            $this->warnings[] = 'DOKU_NOTIFICATION_URL bukan https; dashboard DOKU menolak URL non-https di produksi.';
        }
    }

    private function checkCallbackUrl(string $url): void
    {
        if ($url === '') {
            $this->warnings[] = 'DOKU_CALLBACK_URL kosong — tamu tidak dikembalikan ke situs setelah membayar.';

            return;
        }

        if (parse_url($url, PHP_URL_PATH) !== '/payment/callback') {
            $this->warnings[] = 'Path DOKU_CALLBACK_URL bukan /payment/callback; halaman kembali tidak akan mengenali pemesanan.';
        }
    }

    private function checkPaymentWindow(int $dueMinutes, int $holdMinutes): void
    {
        if ($dueMinutes < 1) {
            $this->problems[] = 'DOKU_PAYMENT_DUE_MINUTES harus minimal 1.';
        }

        if ($dueMinutes > $holdMinutes) {
            $this->problems[] = "DOKU_PAYMENT_DUE_MINUTES ({$dueMinutes}) melebihi durasi hold ({$holdMinutes} menit). "
                .'Halaman pembayaran akan tetap hidup setelah kamar dilepas → pembayaran terlambat untuk kamar yang sudah dijual.';
        }

        // booking_hold_minutes lives in system_settings, NOT in .env.
        if (env('BOOKING_HOLD_MINUTES') !== null && (int) env('BOOKING_HOLD_MINUTES') !== $holdMinutes) {
            $this->warnings[] = 'BOOKING_HOLD_MINUTES di .env tidak dibaca aplikasi. Durasi hold berasal dari tabel system_settings ('
                .$holdMinutes.' menit).';
        }
    }

    /**
     * Taking real money means the app is reachable from the internet, and an
     * internet-reachable Laravel with APP_DEBUG=true publishes its own .env on
     * any stack trace — DOKU secret key and database password included.
     */
    private function checkProductionReadiness(string $environment): void
    {
        if ($environment !== 'production') {
            return;
        }

        if (config('app.debug')) {
            $this->problems[] = 'Mode produksi aktif tetapi APP_DEBUG=true. Halaman galat akan membocorkan '
                .'DOKU_SECRET_KEY, APP_KEY dan kata sandi database ke siapa pun yang memicunya. Set APP_DEBUG=false.';
        }

        if (config('app.env') !== 'production') {
            $this->warnings[] = 'Mode produksi aktif tetapi APP_ENV='.config('app.env')
                .'. Set APP_ENV=production agar Laravel memakai perilaku produksi.';
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $this->warnings[] = 'APP_URL bukan https. Pembayaran sungguhan harus melalui domain https milik Anda, '
                .'bukan tunnel sementara — URL ngrok berubah dan mematikan notifikasi.';
        }
    }
}
