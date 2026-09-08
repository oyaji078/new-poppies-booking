<?php

namespace Tests\Feature\Payments;

use App\Enums\UserRole;
use App\Livewire\Admin\SettingsManager;
use App\Models\Booking;
use App\Models\User;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pay-at-hotel takes a room off sale against nothing but a promise, so whether
 * guests may choose it is a Super Admin decision made in the settings screen —
 * not a code change.
 */
class CashPaymentToggleTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
    }

    public function test_a_super_admin_can_turn_pay_at_hotel_off(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(SettingsManager::class)
            ->set('cash_payment_enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(app(SettingService::class)->boolean('cash_payment_enabled', true));
    }

    public function test_the_setting_survives_a_round_trip_through_the_screen(): void
    {
        app(SettingService::class)->set('cash_payment_enabled', false, 'boolean', 'booking');

        Livewire::actingAs($this->superAdmin())
            ->test(SettingsManager::class)
            ->assertSet('cash_payment_enabled', false);
    }

    public function test_the_public_booking_page_hides_the_button_when_it_is_off(): void
    {
        app(SettingService::class)->set('cash_payment_enabled', false, 'boolean', 'booking');
        $booking = Booking::factory()->create();

        $this->withSession(['booking_access.'.$booking->code => $booking->customer_email])
            ->get(route('booking.show', $booking->code))
            ->assertOk()
            ->assertDontSee('Bayar di Tempat');
    }

    public function test_the_public_booking_page_offers_the_button_when_it_is_on(): void
    {
        app(SettingService::class)->set('cash_payment_enabled', true, 'boolean', 'booking');
        $booking = Booking::factory()->create();

        $this->withSession(['booking_access.'.$booking->code => $booking->customer_email])
            ->get(route('booking.show', $booking->code))
            ->assertOk()
            ->assertSee('Bayar di Tempat');
    }

    public function test_ordinary_staff_cannot_reach_the_settings_screen(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->get(route('admin.settings.edit'))
            ->assertForbidden();
    }
}
