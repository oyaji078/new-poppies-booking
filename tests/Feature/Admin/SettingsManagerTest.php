<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\SettingsManager;
use App\Models\User;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsManagerTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
    }

    public function test_only_a_super_admin_may_open_settings(): void
    {
        $this->get('/admin/pengaturan')->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->get('/admin/pengaturan')->assertForbidden();

        $this->actingAs($this->superAdmin())->get('/admin/pengaturan')->assertOk();
    }

    public function test_the_menu_is_hidden_from_ordinary_admins(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->get('/admin')->assertOk()->assertDontSee('Pengaturan');

        $this->actingAs($this->superAdmin())
            ->get('/admin')->assertOk()->assertSee('Pengaturan');
    }

    public function test_saving_persists_typed_settings(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(SettingsManager::class)
            ->set('hotel_name', 'Villa Uji')
            ->set('tax_percent', 12)
            ->set('cancellation_fee_percent', 40)
            ->set('booking_hold_minutes', 45)
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(SettingService::class);
        $this->assertSame('Villa Uji', $settings->get('hotel_name'));
        $this->assertSame(12, $settings->integer('tax_percent'));
        $this->assertSame(40, $settings->integer('cancellation_fee_percent'));
    }

    public function test_hold_shorter_than_the_doku_window_is_rejected(): void
    {
        config(['doku.payment_due_minutes' => 30]);

        Livewire::actingAs($this->superAdmin())
            ->test(SettingsManager::class)
            ->set('booking_hold_minutes', 20) // shorter than 30
            ->call('save')
            ->assertHasErrors('booking_hold_minutes');
    }

    public function test_out_of_range_values_are_rejected(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(SettingsManager::class)
            ->set('tax_percent', 250)
            ->set('cancellation_fee_percent', -5)
            ->call('save')
            ->assertHasErrors(['tax_percent', 'cancellation_fee_percent']);
    }
}
