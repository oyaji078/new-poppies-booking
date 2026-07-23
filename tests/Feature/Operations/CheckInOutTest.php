<?php

namespace Tests\Feature\Operations;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Operations\CheckInService;
use App\Services\Operations\CheckOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CheckInOutTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $roomType;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->roomType = RoomType::factory()->create(['default_inventory' => 3]);
    }

    private function confirmedBooking(?string $checkIn = null, ?string $checkOut = null, int $rooms = 1): Booking
    {
        $checkIn ??= today()->toDateString();
        $checkOut ??= today()->addDays(2)->toDateString();

        $booking = Booking::factory()->create([
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'rooms' => $rooms,
            'held_until' => null,
            'confirmed_at' => now(),
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'room_type_id' => $this->roomType->id,
            'room_type_name' => $this->roomType->name,
            'rooms' => $rooms,
            'subtotal_amount' => 1_000_000,
        ]);

        return $booking->fresh('items');
    }

    public function test_admin_can_check_in_and_assign_a_physical_room(): void
    {
        $room = Room::factory()->create(['room_type_id' => $this->roomType->id, 'room_number' => 'STD-101']);
        $booking = $this->confirmedBooking();
        $item = $booking->items->first();

        $result = app(CheckInService::class)->checkIn($booking, [$item->id => [$room->id]], $this->admin);

        $this->assertSame(BookingStatus::CHECKED_IN, $result->status);
        $this->assertNotNull($result->checked_in_at);
        $this->assertDatabaseHas('room_assignments', [
            'booking_item_id' => $item->id, 'room_id' => $room->id,
        ]);
    }

    public function test_the_same_physical_room_cannot_host_two_overlapping_stays(): void
    {
        $room = Room::factory()->create(['room_type_id' => $this->roomType->id, 'room_number' => 'STD-102']);
        $service = app(CheckInService::class);

        $first = $this->confirmedBooking(today()->toDateString(), today()->addDays(3)->toDateString());
        $service->checkIn($first, [$first->items->first()->id => [$room->id]], $this->admin);

        // Overlapping stay for the same room.
        $second = $this->confirmedBooking(today()->addDay()->toDateString(), today()->addDays(4)->toDateString());

        $this->expectException(RuntimeException::class);
        $service->checkIn($second, [$second->items->first()->id => [$room->id]], $this->admin);
    }

    public function test_back_to_back_stays_are_allowed_because_checkout_day_is_exclusive(): void
    {
        $room = Room::factory()->create(['room_type_id' => $this->roomType->id, 'room_number' => 'STD-103']);
        $service = app(CheckInService::class);

        // Guest A: today -> +2 (leaves on the +2)
        $first = $this->confirmedBooking(today()->toDateString(), today()->addDays(2)->toDateString());
        $service->checkIn($first, [$first->items->first()->id => [$room->id]], $this->admin);
        app(CheckOutService::class)->checkOut($first->fresh('items'), $this->admin);

        // Guest B arrives on the day A leaves — no conflict.
        // (Their stay starts in the future, so an early check-in reason is required.)
        $second = $this->confirmedBooking(today()->addDays(2)->toDateString(), today()->addDays(4)->toDateString());
        $result = $service->checkIn(
            $second,
            [$second->items->first()->id => [$room->id]],
            $this->admin,
            earlyReason: 'Tamu tiba lebih awal, kamar sudah siap.'
        );

        $this->assertSame(BookingStatus::CHECKED_IN, $result->status);
    }

    public function test_room_must_belong_to_the_booked_room_type(): void
    {
        $otherType = RoomType::factory()->create();
        $wrongRoom = Room::factory()->create(['room_type_id' => $otherType->id]);
        $booking = $this->confirmedBooking();

        $this->expectException(RuntimeException::class);
        app(CheckInService::class)->checkIn($booking, [$booking->items->first()->id => [$wrongRoom->id]], $this->admin);
    }

    public function test_room_under_maintenance_cannot_be_assigned(): void
    {
        $room = Room::factory()->maintenance()->create(['room_type_id' => $this->roomType->id]);
        $booking = $this->confirmedBooking();

        $this->expectException(RuntimeException::class);
        app(CheckInService::class)->checkIn($booking, [$booking->items->first()->id => [$room->id]], $this->admin);
    }

    public function test_must_assign_exactly_the_booked_number_of_rooms(): void
    {
        $rooms = Room::factory()->count(2)->create(['room_type_id' => $this->roomType->id]);
        $booking = $this->confirmedBooking(rooms: 2);

        // Only one room supplied for a 2-room booking.
        $this->expectException(RuntimeException::class);
        app(CheckInService::class)->checkIn($booking, [$booking->items->first()->id => [$rooms[0]->id]], $this->admin);
    }

    public function test_unconfirmed_booking_cannot_check_in(): void
    {
        $room = Room::factory()->create(['room_type_id' => $this->roomType->id]);
        $booking = $this->confirmedBooking();
        $booking->forceFill(['status' => BookingStatus::HELD])->save();

        $this->expectException(RuntimeException::class);
        app(CheckInService::class)->checkIn($booking->fresh('items'), [$booking->items->first()->id => [$room->id]], $this->admin);
    }

    public function test_early_check_in_requires_a_reason(): void
    {
        $room = Room::factory()->create(['room_type_id' => $this->roomType->id]);
        $booking = $this->confirmedBooking(today()->addDays(3)->toDateString(), today()->addDays(5)->toDateString());

        $this->expectException(RuntimeException::class);
        app(CheckInService::class)->checkIn($booking, [$booking->items->first()->id => [$room->id]], $this->admin);
    }

    public function test_check_out_closes_assignments_and_frees_the_room(): void
    {
        $room = Room::factory()->create(['room_type_id' => $this->roomType->id]);
        $booking = $this->confirmedBooking();
        app(CheckInService::class)->checkIn($booking, [$booking->items->first()->id => [$room->id]], $this->admin);

        $result = app(CheckOutService::class)->checkOut($booking->fresh('items'), $this->admin);

        $this->assertSame(BookingStatus::CHECKED_OUT, $result->status);
        $this->assertNotNull($result->checked_out_at);
        $this->assertNotNull(RoomAssignment::where('room_id', $room->id)->first()->check_out_at);
    }

    public function test_only_checked_in_bookings_can_check_out(): void
    {
        $booking = $this->confirmedBooking();

        $this->expectException(RuntimeException::class);
        app(CheckOutService::class)->checkOut($booking, $this->admin);
    }

    public function test_no_show_requires_reason_and_only_applies_to_confirmed(): void
    {
        $booking = $this->confirmedBooking();
        $service = app(CheckOutService::class);

        $this->assertSame(BookingStatus::NO_SHOW, $service->markNoShow($booking, $this->admin, 'Tamu tidak datang')->status);

        $other = $this->confirmedBooking();
        $this->expectException(RuntimeException::class);
        $service->markNoShow($other, $this->admin, '  ');
    }
}
