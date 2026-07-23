<?php

namespace App\Console\Commands;

use App\Services\Doku\DokuConfigurationCheck;
use App\Services\Doku\DokuEnvironmentService;
use App\Services\Doku\DokuSignatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Pre-flight check for the DOKU integration.
 *
 * Every failure listed here is something that breaks a real payment silently:
 * a notification URL DOKU cannot reach, a payment page that outlives the
 * inventory hold, sandbox credentials pointed at the production host, and so on.
 *
 *   php artisan doku:check           # configuration only, no network calls
 *   php artisan doku:check --ping    # also create a throwaway checkout at DOKU
 */
class DokuCheck extends Command
{
    protected $signature = 'doku:check
        {--ping : Create a real (throwaway) checkout to verify the credentials}
        {--environment= : Periksa mode tertentu (sandbox|production) tanpa mengalihkan mode aktif}';

    protected $description = 'Verify the DOKU configuration and, optionally, the credentials against DOKU itself';

    public function handle(DokuConfigurationCheck $check, DokuEnvironmentService $environments): int
    {
        $active = $environments->active();
        $environment = $active;

        // Validating an environment BEFORE switching to it is the normal case, so
        // this only rebinds config for the duration of the command — the stored
        // active mode is untouched.
        if ($requested = $this->option('environment')) {
            if (! in_array($requested, $environments->available(), true)) {
                $this->components->error("Mode \"{$requested}\" tidak dikenal. Pilihan: ".implode(', ', $environments->available()));

                return self::FAILURE;
            }

            $environment = (string) $requested;
            $environments->apply($environment);
        }

        $result = $check->run();

        $this->components->info('Konfigurasi DOKU — memeriksa mode: '.strtoupper($environment));

        if ($environment !== $active) {
            $this->components->warn(
                "Ini hanya pratinjau. Mode aktif tetap {$active} — mode tidak berubah oleh perintah ini."
            );
        }

        $rows = [];
        foreach ($result['summary'] as $key => $value) {
            $rows[] = [$key, $value];
        }
        $this->table(['Kunci', 'Nilai'], $rows);

        // Show which environments are ready, so a switch can be planned.
        $this->line('  Mode tersedia:');
        foreach ($environments->available() as $name) {
            $this->line(sprintf(
                '  %s %-11s %s',
                $name === $active ? '●' : '○',
                $name,
                $environments->isConfigured($name) ? 'kredensial lengkap' : '<comment>kredensial belum lengkap</comment>',
            ));
        }
        $this->newLine();

        foreach ($result['warnings'] as $warning) {
            $this->components->warn($warning);
        }

        foreach ($result['problems'] as $problem) {
            $this->components->error($problem);
        }

        if ($result['passes']) {
            $this->components->info('Konfigurasi DOKU lolos pemeriksaan.');
        }

        // --ping only needs credentials and the base URL to be sane. A notification
        // URL that is not public yet is normal mid-setup and must not block the
        // credential test — it still counts against the final verdict.
        if ($this->option('ping') && $result['credentials_usable']) {
            $pingResult = $this->ping($environment);

            if (! $result['passes']) {
                $this->newLine();
                $this->components->error('Konfigurasi DOKU BELUM siap. Perbaiki poin di atas lalu jalankan ulang.');

                return self::FAILURE;
            }

            return $pingResult;
        }

        if (! $result['passes']) {
            $this->newLine();
            $this->components->error('Konfigurasi DOKU BELUM siap. Perbaiki poin di atas lalu jalankan ulang.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('  Uji kredensial ke DOKU: <comment>php artisan doku:check --ping</comment>');
        $this->line('  Uji notifikasi lokal:   <comment>php artisan doku:simulate-notification {invoice}</comment>');
        $this->line('  Ganti mode (Super Admin): <comment>/admin/doku</comment>');

        return self::SUCCESS;
    }

    /**
     * Create a throwaway checkout so the credentials are proven end to end.
     * Nothing in the database is touched — this is a pure API call.
     */
    private function ping(string $environment): int
    {
        if ($environment === 'production' && ! $this->confirm('DOKU_ENVIRONMENT=production. Tetap kirim permintaan uji ke DOKU produksi?', false)) {
            $this->components->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        $signatures = app(DokuSignatureService::class);
        $target = (string) config('doku.checkout_path');
        $requestId = (string) Str::uuid();
        $timestamp = $signatures->timestamp();

        $body = [
            'order' => [
                'invoice_number' => 'PING-'.now()->format('YmdHis'),
                'amount' => 10000,
                'currency' => (string) config('doku.currency', 'IDR'),
                'callback_url' => (string) config('doku.callback_url'),
                'language' => 'ID',
                'line_items' => [[
                    'name' => 'Uji koneksi',
                    'quantity' => 1,
                    'price' => 10000,
                ]],
            ],
            'payment' => ['payment_due_date' => 10],
            'customer' => [
                'id' => 'PING',
                'name' => 'Uji Koneksi',
                'email' => 'ping@example.test',
                'country' => 'ID',
            ],
        ];

        $raw = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->components->task('Mengirim permintaan checkout uji ke DOKU', function () use (&$response, $signatures, $requestId, $timestamp, $target, $raw) {
            $response = Http::withHeaders([
                'Client-Id' => (string) config('doku.client_id'),
                'Request-Id' => $requestId,
                'Request-Timestamp' => $timestamp,
                'Signature' => $signatures->sign($requestId, $timestamp, $target, $signatures->digest($raw)),
                'Content-Type' => 'application/json',
            ])
                ->timeout((int) config('doku.timeout', 30))
                ->withBody($raw, 'application/json')
                ->post(config('doku.base_url').$target);

            return $response->successful();
        });

        $json = $response?->json() ?? [];

        if (! $response || ! $response->successful()) {
            $this->newLine();
            $this->components->error('DOKU menolak permintaan (HTTP '.($response?->status() ?? '-').').');
            $this->line('  '.json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->newLine();
            $this->line('  <comment>Petunjuk:</comment>');

            foreach ($this->hintsFor((string) data_get($json, 'error.code'), $response->status()) as $hint) {
                $this->line('  • '.$hint);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Kredensial DOKU valid.');
        $this->line('  Payment URL uji: <comment>'.data_get($json, 'response.payment.url').'</comment>');
        $this->newLine();
        $this->line('  Halaman itu boleh diabaikan — tidak ada pemesanan yang dibuat.');
        $this->line('  Langkah berikutnya: daftarkan Notification URL di dashboard DOKU →');
        $this->line('  <comment>'.config('doku.notification_url').'</comment>');

        return self::SUCCESS;
    }

    /**
     * Translate DOKU's rejection into the thing that is actually misconfigured.
     *
     * @return array<int, string>
     */
    private function hintsFor(string $errorCode, int $httpStatus): array
    {
        return match (true) {
            $errorCode === 'invalid_client_id' => [
                'DOKU tidak mengenali Client-Id ini pada host '.config('doku.base_url').'.',
                'Penyebab tersering: kredensial produksi dipakai pada host sandbox (atau sebaliknya).',
                'Sandbox adalah lingkungan terpisah dengan pendaftaran sendiri — akun dashboard.doku.com',
                'tidak berlaku di sana. Daftar: https://sandbox.doku.com/bo/sandbox-registration',
                'Kredensial: https://sandbox.doku.com/bo/developer/api-keys (Settings → API Keys → Reveal Key).',
                'Secret Key hanya tampil sekitar 30 detik setelah diungkap — salin saat itu juga.',
                'Pastikan tidak ada spasi/baris baru ikut tersalin ke .env.',
            ],
            $errorCode === 'request_time_out_of_range' => [
                'Jam sistem server meleset lebih dari 1 jam dari waktu DOKU.',
                'DOKU menolak Request-Timestamp di luar ±3600 detik.',
                'Sinkronkan jam server (Windows: w32tm /resync) lalu ulangi.',
            ],
            $errorCode === 'invalid_signature' || $httpStatus === 401 => [
                'DOKU_SECRET_KEY tidak cocok dengan Client-Id tersebut.',
                'Salin ulang Secret Key dari dashboard yang sama dengan Client ID.',
            ],
            str_contains($errorCode, 'amount') || str_contains($errorCode, 'line_items') => [
                'Total line_items harus sama persis dengan order.amount.',
            ],
            $httpStatus === 404 => [
                'DOKU_BASE_URL salah — sandbox: https://api-sandbox.doku.com, produksi: https://api.doku.com.',
            ],
            default => [
                'Periksa pesan galat di atas pada dokumentasi DOKU Checkout.',
                'Cocokkan DOKU_ENVIRONMENT, DOKU_BASE_URL dan pasangan Client ID / Secret Key.',
            ],
        };
    }
}
