<?php

namespace Tests\Feature\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Models\User;
use App\Services\Payments\CashPaymentService;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Pay-at-hotel. Reserving and paying are two separate events: the room comes off
 * sale immediately, but the booking only reads as paid once cash is recorded.
 */
class CashPaymentTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $roomType;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->roomType = RoomType::factory()->create(['default_inventory' => 2]);
        Room::factory()->count(2)->create(['room_type_id' => $this->roomType->id]);
    }

    private function heldBooking(int $total = 2_000_000): Booking
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::HELD,
            'payment_status' => PaymentStatus::UNPAID,
            'check_in_date' => today()->addDays(5)->toDateString(),
            'check_out_date' => today()->addDays(7)->toDateString(),
            'rooms' => 1,
            'total_amount' => $total,
            'held_until' => now()->addMinutes(20),
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'room_type_id' => $this->roomType->id,
            'room_type_name' => $this->roomType->name,
            'rooms' => 1,
            'subtotal_amount' => $total,
        ]);

        foreach ($booking->stayPeriod()->stayDateStrings() as $date) {
            RoomTypeInventory::create([
                'room_type_id' => $this->roomType->id,
                'inventory_date' => $date,
                'total_inventory' => 2,
                'blocked_inventory' => 0,
                'held_inventory' => 1,
                'confirmed_inventory' => 0,
            ]);
        }

        return $booking->fresh('items');
    }

    private function inventoryRows(Booking $booking)
    {
        return RoomTypeInventory::query()
            ->where('room_type_id', $this->roomType->id)
            ->whereIn('inventory_date', $booking->stayPeriod()->stayDateStrings())
            ->get();
    }

    public function test_reserving_confirms_the_booking_but_leaves_it_unpaid(): void
    {
        $booking = $this->heldBooking();

        app(CashPaymentService::class)->reserve($booking);

        $fresh = $booking->fresh();
        $this->assertSame(BookingStatus::CONFIRMED, $fresh->status);
        // The room is ours, the money is not.
        $this->assertSame(PaymentStatus::UNPAID, $fresh->payment_status);
        $this->assertNull($fresh->held_until);
        $this->assertNotNull($fresh->confirmed_at);
    }

    public function test_reserving_moves_inventory_from_held_to_confirmed(): void
    {
        $booking = $this->heldBooking();

        app(CashPaymentService::class)->reserve($booking);

        foreach ($this->inventoryRows($booking) as $row) {
            $this->assertSame(0, $row->held_inventory);
            $this->assertSame(1, $row->confirmed_inventory);
        }
    }

    public function test_an_expired_hold_cannot_be_reserved_as_cash(): void
    {
        $booking = $this->heldBooking();
        $booking->forceFill(['held_until' => now()->subMinute()])->save();

        $this->expectException(RuntimeException::class);
        app(CashPaymentService::class)->reserve($booking->fresh('items'));
    }

    public function test_cash_can_be_turned_off(): void
    {
        app(SettingService::class)->set('cash_payment_enabled', false, 'boolean');
        $booking = $this->heldBooking();

        $this->expectException(RuntimeException::class);
        app(CashPaymentService::class)->reserve($booking);
    }

    public function test_recording_the_full_amount_marks_the_booking_paid(): void
    {
        $booking = $this->heldBooking();
        $service = app(CashPaymentService::class);
        $service->reserve($booking);

        $service->recordPayment($booking->fresh(), $this->admin, 2_000_000, 'Tunai di resepsionis');

        $fresh = $booking->fresh();
        $this->assertSame(PaymentStatus::PAID, $fresh->payment_status);
        $this->assertSame(0, $service->outstanding($fresh));
        $this->assertDatabaseHas('payment_attempts', [
            'booking_id' => $booking->id,
            'provider' => 'cash',
            'amount' => 2_000_000,
        ]);
    }

    public function test_a_partial_payment_leaves_a_balance_outstanding(): void
    {
        $booking = $this->heldBooking();
        $service = app(CashPaymentService::class);
        $service->reserve($booking);

        $service->recordPayment($booking->fresh(), $this->admin, 500_000);

        $fresh = $booking->fresh();
        $this->assertSame(PaymentStatus::PENDING, $fresh->payment_status);
        $this->assertSame(1_500_000, $service->outstanding($fresh));

        // Settling the rest completes it.
        $service->recordPayment($fresh, $this->admin, 1_500_000);
        $this->assertSame(PaymentStatus::PAID, $booking->fresh()->payment_status);
    }

    public function test_cash_cannot_exceed_the_outstanding_balance(): void
    {
        $booking = $this->heldBooking();
        $service = app(CashPaymentService::class);
        $service->reserve($booking);

        $this->expectException(RuntimeException::class);
        $service->recordPayment($booking->fresh(), $this->admin, 2_500_000);
    }

    public function test_cash_cannot_be_recorded_for_a_booking_that_is_not_reserved(): void
    {
        $booking = $this->heldBooking();

        $this->expectException(RuntimeException::class);
        app(CashPaymentService::class)->recordPayment($booking, $this->admin, 1_000_000);
    }

    public function test_the_guest_route_requires_verified_access(): void
    {
        $booking = $this->heldBooking();

        // No code+email session grant → forbidden, and nothing changes.
        $this->post(route('payment.cash', $booking->code))->assertForbidden();

        $this->assertSame(BookingStatus::HELD, $booking->fresh()->status);
    }

    public function test_the_guest_can_choose_pay_at_hotel_from_the_booking_page(): void
    {
        $booking = $this->heldBooking();

        $this->withSession(['booking_access.'.$booking->code => $booking->customer_email])
            ->post(route('payment.cash', $booking->code))
            ->assertRedirect(route('booking.show', $booking->code));

        $this->assertSame(BookingStatus::CONFIRMED, $booking->fresh()->status);
        $this->assertSame(PaymentStatus::UNPAID, $booking->fresh()->payment_status);
    }
}
