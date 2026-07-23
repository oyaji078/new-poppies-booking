<?php

namespace Tests\Feature\Doku;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Livewire\Admin\DokuEnvironmentSwitcher;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Doku\DokuEnvironmentService;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The DOKU mode decides whether guests are charged real money, so the guard is
 * the feature. Every non-super-admin role is asserted to be locked out, and a
 * switch is asserted to be impossible without a reason or without credentials.
 */
class DokuEnvironmentSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'doku.default_environment' => 'sandbox',
            'doku.environments.sandbox.client_id' => 'BRN-SANDBOX-1',
            'doku.environments.sandbox.secret_key' => 'SK-sandbox-secret-value',
            'doku.environments.sandbox.base_url' => 'https://api-sandbox.doku.com',
            'doku.environments.production.client_id' => 'BRN-PROD-1',
            'doku.environments.production.secret_key' => 'SK-production-secret-value',
            'doku.environments.production.base_url' => 'https://api.doku.com',
        ]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
    }

    public function test_only_a_super_admin_may_open_the_switcher(): void
    {
        $this->get('/admin/doku')->assertRedirect('/login');

        foreach ([UserRole::CUSTOMER, UserRole::RECEPTIONIST, UserRole::MANAGER, UserRole::ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/admin/doku')->assertForbidden();
        }

        $this->actingAs($this->superAdmin())->get('/admin/doku')->assertOk();
    }

    public function test_the_sidebar_link_is_hidden_from_ordinary_admins(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Mode Pembayaran DOKU');

        $this->actingAs($this->superAdmin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Mode Pembayaran DOKU');
    }

    public function test_a_super_admin_can_switch_and_the_change_takes_effect(): void
    {
        $admin = $this->superAdmin();
        $environments = app(DokuEnvironmentService::class);

        $this->assertSame('sandbox', $environments->active());

        Livewire::actingAs($admin)
            ->test(DokuEnvironmentSwitcher::class)
            ->call('startSwitch', 'production')
            ->set('reason', 'Go-live setelah verifikasi kredensial produksi')
            ->call('confirmSwitch')
            ->assertHasNoErrors();

        $this->assertSame('production', $environments->active());

        // The resolved credentials really moved, not just the label.
        $environments->apply();
        $this->assertSame('https://api.doku.com', config('doku.base_url'));
        $this->assertSame('BRN-PROD-1', config('doku.client_id'));
    }

    public function test_every_switch_is_audited_with_actor_and_reason(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(DokuEnvironmentSwitcher::class)
            ->call('startSwitch', 'production')
            ->set('reason', 'Mulai menerima pembayaran nyata')
            ->call('confirmSwitch')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DOKU_ENVIRONMENT_SWITCHED->value,
            'user_id' => $admin->id,
        ]);

        $entry = AuditLog::where('action', AuditAction::DOKU_ENVIRONMENT_SWITCHED->value)->latest('id')->first();
        $this->assertSame('sandbox', data_get($entry->old_values, 'environment'));
        $this->assertSame('production', data_get($entry->new_values, 'environment'));
        $this->assertSame('Mulai menerima pembayaran nyata', data_get($entry->new_values, 'reason'));
    }

    public function test_a_switch_without_a_reason_is_refused(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(DokuEnvironmentSwitcher::class)
            ->call('startSwitch', 'production')
            ->set('reason', '')
            ->call('confirmSwitch')
            ->assertHasErrors('reason');

        $this->assertSame('sandbox', app(DokuEnvironmentService::class)->active());
    }

    public function test_switching_to_an_unconfigured_environment_is_refused(): void
    {
        // Production credentials were never filled in.
        config([
            'doku.environments.production.client_id' => null,
            'doku.environments.production.secret_key' => null,
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(DokuEnvironmentSwitcher::class)
            ->call('startSwitch', 'production')
            ->set('reason', 'Mencoba pindah tanpa kredensial')
            ->call('confirmSwitch')
            ->assertHasErrors('target');

        $this->assertSame('sandbox', app(DokuEnvironmentService::class)->active());
    }

    public function test_the_service_refuses_a_non_super_admin_even_if_the_ui_is_bypassed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->expectException(\RuntimeException::class);

        app(DokuEnvironmentService::class)->switchTo('production', $admin, 'mencoba menembus UI');
    }

    public function test_a_super_admin_can_choose_the_payment_methods(): void
    {
        config(['doku.known_payment_method_types' => ['QRIS', 'VIRTUAL_ACCOUNT_BCA', 'CREDIT_CARD']]);

        Livewire::actingAs($this->superAdmin())
            ->test(DokuEnvironmentSwitcher::class)
            ->set('methods', ['QRIS', 'VIRTUAL_ACCOUNT_BCA', 'BUKAN_METODE'])
            ->call('saveMethods')
            ->assertHasNoErrors();

        // Only known methods are stored; the junk value is dropped.
        $this->assertSame(
            'QRIS,VIRTUAL_ACCOUNT_BCA',
            app(SettingService::class)->get('doku_payment_methods')
        );
    }

    public function test_an_unknown_stored_environment_falls_back_to_the_default(): void
    {
        app(SettingService::class)
            ->set(DokuEnvironmentService::SETTING_KEY, 'staging', 'string', 'payment');

        // A junk value must never leave the gateway pointed somewhere unintended.
        $this->assertSame('sandbox', app(DokuEnvironmentService::class)->active());
    }
}
