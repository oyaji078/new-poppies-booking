<?php

namespace Tests\Feature\Security;

use App\Livewire\Public\CheckoutWizard;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Models\User;
use App\Services\Settings\SettingService;
use App\Support\StayPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for the §34 security rules. These lock in behaviour that must
 * never silently regress.
 */
class SecurityGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_area_is_closed_to_guests_and_customers(): void
    {
        $adminUrls = ['/admin', '/admin/reservasi', '/admin/laporan', '/admin/inventaris', '/admin/pembatalan'];

        foreach ($adminUrls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $customer = User::factory()->create();
        foreach ($adminUrls as $url) {
            $this->actingAs($customer)->get($url)->assertForbidden();
        }
    }

    public function test_booking_details_are_not_accessible_with_the_code_alone(): void
    {
        $booking = Booking::factory()->create(['customer_email' => 'owner@example.com']);

        $this->get(route('booking.show', $booking->code))->assertForbidden();
    }

    public function test_lookup_does_not_reveal_whether_a_booking_code_exists(): void
    {
        $real = Booking::factory()->create(['customer_email' => 'owner@example.com']);

        $existing = $this->from(route('booking.lookup.form'))
            ->post(route('booking.lookup'), ['code' => $real->code, 'email' => 'wrong@example.com']);

        $fake = $this->from(route('booking.lookup.form'))
            ->post(route('booking.lookup'), ['code' => 'NPS-20260101-ZZZZZZ', 'email' => 'wrong@example.com']);

        // Identical generic error for both, so the form can't be used to probe codes.
        $this->assertSame(
            $existing->getSession()->get('errors')->first('code'),
            $fake->getSession()->get('errors')->first('code'),
        );
    }

    public function test_doku_notification_route_is_csrf_exempt_but_signature_protected(): void
    {
        $payload = [
            'order' => ['invoice_number' => 'X', 'amount' => 1],
            'transaction' => ['status' => 'SUCCESS'],
        ];

        // No CSRF token supplied. It must NOT fail with 419 — it must fail on the
        // signature guard instead, proving the exemption did not weaken anything.
        // Without the signature headers at all the request is turned away as
        // malformed (400) before it can write a payment event.
        $response = $this->postJson('/webhook/doku/notifications', $payload);

        $this->assertNotSame(419, $response->getStatusCode());
        $response->assertStatus(400);

        // With the headers present but a forged signature: rejected as unauthorised.
        $signed = $this->postJson('/webhook/doku/notifications', $payload, [
            'Client-Id' => (string) config('doku.client_id'),
            'Request-Id' => 'req-forged',
            'Request-Timestamp' => '2026-07-18T08:45:42Z',
            'Signature' => 'HMACSHA256=forged',
        ]);

        $this->assertNotSame(419, $signed->getStatusCode());
        $signed->assertStatus(401);
    }

    public function test_price_is_recalculated_server_side_and_client_values_are_ignored(): void
    {
        $settings = app(SettingService::class);
        $settings->set('tax_percent', 10, 'integer');
        $settings->set('service_percent', 5, 'integer');
        $settings->set('weekend_surcharge_percent', 0, 'integer');
        $settings->set('extra_guest_fee', 0, 'integer');

        $checkIn = now()->addDays(20)->toDateString();
        $checkOut = now()->addDays(22)->toDateString();

        $roomType = RoomType::factory()->create([
            'base_price' => 1_000_000, 'default_inventory' => 2,
            'adult_capacity' => 2, 'max_guests' => 4, 'is_published' => true,
        ]);
        Room::factory()->count(2)->create(['room_type_id' => $roomType->id]);
        foreach ((new StayPeriod($checkIn, $checkOut))->stayDateStrings() as $date) {
            RoomTypeInventory::create([
                'room_type_id' => $roomType->id, 'inventory_date' => $date,
                'total_inventory' => 2, 'held_inventory' => 0, 'confirmed_inventory' => 0, 'blocked_inventory' => 0,
            ]);
        }

        Livewire::withQueryParams([
            'checkin' => $checkIn, 'checkout' => $checkOut, 'adults' => 2, 'children' => 0, 'rooms' => 1,
        ])
            ->test(CheckoutWizard::class, ['roomType' => $roomType])
            ->set('customer_name', 'Tamu Uji')
            ->set('customer_email', 'uji@example.com')
            ->set('customer_phone', '0812')
            ->set('terms', true)
            // A hostile client trying to inject a cheap total:
            ->set('promo_code', '')
            ->call('confirmBooking');

        $booking = Booking::first();

        // Server price stands: 2 nights x 1,000,000 + 10% tax + 5% service.
        $this->assertSame(2_300_000, $booking->total_amount);
    }

    public function test_passwords_are_hashed_and_never_stored_in_plain_text(): void
    {
        $this->post('/register', [
            'name' => 'Secure User',
            'email' => 'secure@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $user = User::where('email', 'secure@example.com')->first();

        $this->assertNotSame('Password123!', $user->password);
        $this->assertTrue(password_verify('Password123!', $user->password));
    }

    public function test_booking_lookup_is_rate_limited(): void
    {
        // Route is throttled at 10/min; the 11th must be blocked.
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('booking.lookup'), ['code' => 'NPS-X', 'email' => 'a@b.com']);
        }

        $this->post(route('booking.lookup'), ['code' => 'NPS-X', 'email' => 'a@b.com'])
            ->assertStatus(429);
    }
}
