<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use App\Services\Doku\PaymentCallbackToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "waiting for confirmation" pages poll this endpoint so they refresh the
 * moment the server-to-server notification lands, instead of showing a stale
 * status until the guest reloads by hand.
 */
class PaymentStatusPollTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_endpoint_reflects_the_current_booking_state(): void
    {
        $booking = Booking::factory()->create([
            'customer_email' => 'owner@example.com',
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        // The guest proved access to this booking (code + email) earlier.
        $this->withSession(['booking_access.'.$booking->code => 'owner@example.com'])
            ->getJson(route('booking.status', $booking->code))
            ->assertOk()
            ->assertJson(['status' => 'pending_payment', 'payment_status' => 'pending']);

        // Once the notification confirms it, the same poll sees the new state —
        // which is what flips the page from "waiting" to "success".
        $booking->update([
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        $this->withSession(['booking_access.'.$booking->code => 'owner@example.com'])
            ->getJson(route('booking.status', $booking->code))
            ->assertOk()
            ->assertJson(['status' => 'confirmed', 'payment_status' => 'paid']);
    }

    public function test_status_endpoint_is_closed_to_someone_without_access(): void
    {
        $booking = Booking::factory()->create(['customer_email' => 'owner@example.com']);

        // No session grant, not the owner, not staff.
        $this->getJson(route('booking.status', $booking->code))->assertForbidden();
    }

    public function test_owner_and_staff_may_read_the_status(): void
    {
        $owner = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $booking = Booking::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->getJson(route('booking.status', $booking->code))->assertOk();

        $staff = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->actingAs($staff)->getJson(route('booking.status', $booking->code))->assertOk();
    }

    public function test_the_waiting_page_carries_the_auto_refresh_poller(): void
    {
        $booking = Booking::factory()->create([
            'customer_email' => 'owner@example.com',
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $this->withSession(['booking_access.'.$booking->code => 'owner@example.com'])
            ->get(route('payment.callback', ['code' => $booking->code]))
            ->assertOk()
            ->assertSee('Pembayaran sedang diperiksa')
            ->assertSee('data-payment-status-poller="'.$booking->code.'"', false);
    }

    public function test_returning_payer_without_a_session_is_authorised_by_the_callback_token(): void
    {
        // Simulates coming back from DOKU on a different host: no session grant,
        // but the callback URL carries the signed token. Before the fix this
        // showed a permanent "waiting" screen for an already-paid booking.
        $booking = Booking::factory()->create([
            'customer_email' => 'owner@example.com',
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        $token = app(PaymentCallbackToken::class)->for($booking);

        $this->get(route('payment.callback', ['code' => $booking->code, 't' => $token]))
            ->assertOk()
            ->assertSee('Pembayaran Berhasil')
            ->assertDontSee('Pembayaran sedang diperiksa');
    }

    public function test_an_invalid_callback_token_grants_nothing(): void
    {
        $booking = Booking::factory()->create([
            'customer_email' => 'owner@example.com',
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        // Forged token, and a valid token minted for a different booking.
        $otherToken = app(PaymentCallbackToken::class)
            ->for(Booking::factory()->create(['customer_email' => 'x@example.com']));

        foreach (['t' => 'forged.token.value', 't2' => $otherToken] as $bad) {
            $this->get(route('payment.callback', ['code' => $booking->code, 't' => $bad]))
                ->assertOk()
                // No access → generic waiting screen, never leaks the paid state.
                ->assertSee('Pembayaran sedang diperiksa')
                ->assertDontSee('Pembayaran Berhasil');
        }
    }

    public function test_a_confirmed_page_does_not_poll(): void
    {
        $booking = Booking::factory()->create([
            'customer_email' => 'owner@example.com',
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        $this->withSession(['booking_access.'.$booking->code => 'owner@example.com'])
            ->get(route('payment.callback', ['code' => $booking->code]))
            ->assertOk()
            ->assertSee('Pembayaran Berhasil')
            ->assertDontSee('data-payment-status-poller', false);
    }
}
