<?php

namespace Tests\Feature\Account;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function bookingFor(?User $user, string $code): Booking
    {
        $roomType = RoomType::factory()->create(['name' => 'Deluxe Room']);
        $booking = Booking::factory()->create([
            'user_id' => $user?->id,
            'code' => $code,
            'status' => BookingStatus::CONFIRMED,
        ]);
        BookingItem::create([
            'booking_id' => $booking->id, 'room_type_id' => $roomType->id,
            'room_type_name' => $roomType->name, 'rooms' => 1, 'subtotal_amount' => 500_000,
        ]);

        return $booking;
    }

    public function test_guests_must_log_in_to_see_their_history(): void
    {
        $this->get(route('account.bookings'))->assertRedirect(route('login'));
    }

    public function test_a_customer_sees_only_their_own_bookings(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();

        $mine = $this->bookingFor($me, 'NPS-MINE-0001');
        $theirs = $this->bookingFor($someoneElse, 'NPS-THEM-0002');

        $this->actingAs($me)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('NPS-MINE-0001')
            ->assertSee('Deluxe Room')
            ->assertDontSee('NPS-THEM-0002');
    }

    public function test_the_empty_state_is_shown_when_there_are_no_bookings(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Belum ada pemesanan');
    }

    public function test_the_account_link_appears_for_a_signed_in_customer(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Pemesanan Saya');
    }
}
