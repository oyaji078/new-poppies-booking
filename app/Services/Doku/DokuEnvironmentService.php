<?php

namespace App\Services\Doku;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Decides which DOKU environment is live, and switches between them.
 *
 * The credentials for both environments sit in the environment file and never
 * move. Only the *choice* is stored (in `system_settings.doku_environment`), so
 * flipping sandbox <-> production is a back-office action rather than a deploy —
 * and a secret is never written to the database.
 *
 * Switching decides whether guests are charged real money, so every switch is
 * audited with the actor and a mandatory reason.
 */
class DokuEnvironmentService
{
    public const SETTING_KEY = 'doku_environment';

    public const SANDBOX = 'sandbox';

    public const PRODUCTION = 'production';

    public function __construct(
        private readonly SettingService $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array<int, string>
     */
    public function available(): array
    {
        return array_keys((array) config('doku.environments', []));
    }

    /**
     * The environment currently in force.
     *
     * Falls back to the configured default whenever the stored value is missing
     * or no longer valid — an unreadable setting must never leave the gateway
     * pointed somewhere unintended.
     */
    public function active(): string
    {
        try {
            $stored = (string) $this->settings->get(self::SETTING_KEY, '');
        } catch (Throwable $e) {
            // Settings table not migrated yet (fresh install, first migration).
            return $this->default();
        }

        return in_array($stored, $this->available(), true) ? $stored : $this->default();
    }

    public function default(): string
    {
        $default = (string) config('doku.default_environment', self::SANDBOX);

        return in_array($default, $this->available(), true) ? $default : self::SANDBOX;
    }

    public function isProduction(): bool
    {
        return $this->active() === self::PRODUCTION;
    }

    /**
     * @return array{label: string, base_url: string, client_id: ?string, secret_key: ?string, dashboard_url: string}
     */
    public function credentials(string $environment): array
    {
        $config = config('doku.environments.'.$environment);

        if (! is_array($config)) {
            throw new InvalidArgumentException("Lingkungan DOKU tidak dikenal: {$environment}");
        }

        return [
            'label' => (string) ($config['label'] ?? $environment),
            'base_url' => (string) ($config['base_url'] ?? ''),
            'client_id' => $config['client_id'] !== null ? (string) $config['client_id'] : null,
            'secret_key' => $config['secret_key'] !== null ? (string) $config['secret_key'] : null,
            'dashboard_url' => (string) ($config['dashboard_url'] ?? ''),
        ];
    }

    /**
     * Both keys present. Switching to an environment without credentials would
     * take every payment offline, so the switcher refuses it.
     */
    public function isConfigured(string $environment): bool
    {
        $credentials = $this->credentials($environment);

        return ($credentials['client_id'] ?? '') !== '' && ($credentials['secret_key'] ?? '') !== '';
    }

    /**
     * Point the resolved config keys at the active environment. Called on boot,
     * and again right after a switch so the current request already sees it.
     */
    public function apply(?string $environment = null): void
    {
        $environment ??= $this->active();

        try {
            $credentials = $this->credentials($environment);
        } catch (InvalidArgumentException $e) {
            return;
        }

        config([
            'doku.environment' => $environment,
            'doku.base_url' => $credentials['base_url'],
            'doku.client_id' => $credentials['client_id'],
            'doku.secret_key' => $credentials['secret_key'],
        ]);
    }

    /**
     * Switch the live environment. Returns the environment that was replaced.
     */
    public function switchTo(string $environment, User $actor, string $reason): string
    {
        if (! in_array($environment, $this->available(), true)) {
            throw new InvalidArgumentException("Lingkungan DOKU tidak dikenal: {$environment}");
        }

        if (! $actor->isSuperAdmin()) {
            throw new RuntimeException('Hanya Super Admin yang dapat mengubah mode DOKU.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Alasan perubahan wajib diisi.');
        }

        if (! $this->isConfigured($environment)) {
            throw new RuntimeException(
                'Kredensial '.$environment.' belum lengkap. Isi Client ID dan Secret Key pada .env terlebih dahulu.'
            );
        }

        $previous = $this->active();

        $this->settings->set(
            self::SETTING_KEY,
            $environment,
            'string',
            'payment',
            'Mode DOKU',
            false,
        );

        $this->apply($environment);

        $this->audit->log(
            AuditAction::DOKU_ENVIRONMENT_SWITCHED->value,
            null,
            ['environment' => $previous],
            ['environment' => $environment, 'reason' => $reason],
            $actor,
        );

        Log::warning('DOKU environment switched', [
            'from' => $previous,
            'to' => $environment,
            'actor' => $actor->email,
            'reason' => $reason,
        ]);

        return $previous;
    }
}
