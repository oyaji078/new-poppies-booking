<?php

namespace App\Livewire\Admin;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Services\Doku\DokuConfigurationCheck;
use App\Services\Doku\DokuEnvironmentService;
use App\Services\Settings\SettingService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

/**
 * Super-admin screen for switching DOKU between sandbox and production.
 *
 * Route middleware already restricts access; the role is re-checked here too,
 * because a Livewire action is its own HTTP request and must not rely on the
 * page that rendered it having been guarded.
 */
class DokuEnvironmentSwitcher extends Component
{
    public string $target = '';

    public string $reason = '';

    public bool $confirming = false;

    /** @var array<int, string> */
    public array $methods = [];

    public string $methodStatus = '';

    public function mount(DokuEnvironmentService $environments): void
    {
        $this->authorizeSuperAdmin();
        $this->target = $environments->active();
        $this->methods = (array) config('doku.payment_method_types', []);
    }

    /**
     * Persist the chosen payment methods. Empty = show every method the DOKU
     * account has enabled. Stored in settings and applied at request time.
     */
    public function saveMethods(SettingService $settings): void
    {
        $this->authorizeSuperAdmin();

        $known = (array) config('doku.known_payment_method_types', []);
        $clean = array_values(array_intersect($known, array_map('strval', $this->methods)));

        $settings->set('doku_payment_methods', implode(',', $clean), 'string', 'payment', 'Metode Pembayaran DOKU', false);
        config(['doku.payment_method_types' => $clean]);

        $this->methods = $clean;
        $this->methodStatus = $clean === []
            ? 'Semua metode aktif akan ditampilkan.'
            : 'Metode pembayaran disimpan: '.implode(', ', $clean).'.';
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403, 'Halaman ini hanya dapat diakses oleh Super Admin.');
    }

    public function startSwitch(string $environment): void
    {
        $this->authorizeSuperAdmin();

        $this->target = $environment;
        $this->reason = '';
        $this->resetValidation();
        $this->confirming = true;
    }

    public function cancelSwitch(): void
    {
        $this->confirming = false;
        $this->reason = '';
        $this->resetValidation();
    }

    public function confirmSwitch(DokuEnvironmentService $environments): void
    {
        $this->authorizeSuperAdmin();

        $this->validate([
            'target' => ['required', 'string'],
            // Every override that moves money carries a reason into the audit log.
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan perubahan wajib diisi.',
            'reason.min' => 'Alasan terlalu singkat — tuliskan konteks yang berguna saat diaudit.',
        ]);

        try {
            $previous = $environments->switchTo($this->target, auth()->user(), $this->reason);
        } catch (Throwable $e) {
            $this->addError('target', $e->getMessage());

            return;
        }

        $this->confirming = false;
        $this->reason = '';

        session()->flash('status', "Mode DOKU diubah dari {$previous} ke {$this->target}.");
    }

    public function render(
        DokuEnvironmentService $environments,
        DokuConfigurationCheck $check,
    ): View {
        $active = $environments->active();

        $modes = [];
        foreach ($environments->available() as $name) {
            $credentials = $environments->credentials($name);

            $modes[$name] = [
                'name' => $name,
                'label' => $credentials['label'],
                'base_url' => $credentials['base_url'],
                'client_id' => DokuConfigurationCheck::mask((string) $credentials['client_id']),
                'secret_key' => DokuConfigurationCheck::mask((string) $credentials['secret_key']),
                'dashboard_url' => $credentials['dashboard_url'],
                'configured' => $environments->isConfigured($name),
                'is_active' => $name === $active,
            ];
        }

        return view('livewire.admin.doku-environment-switcher', [
            'active' => $active,
            'modes' => $modes,
            'availableMethods' => (array) config('doku.known_payment_method_types', []),
            'check' => $check->run(),
            'history' => AuditLog::query()
                ->where('action', AuditAction::DOKU_ENVIRONMENT_SWITCHED->value)
                ->with('user')
                ->latest('id')
                ->take(10)
                ->get(),
        ])->layout('components.layouts.admin', [
            'title' => 'Mode Pembayaran DOKU',
            'heading' => 'Mode Pembayaran DOKU',
            'breadcrumb' => 'Admin / Mode Pembayaran DOKU',
        ]);
    }
}
