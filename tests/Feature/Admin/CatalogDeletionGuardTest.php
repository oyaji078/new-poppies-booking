<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Admin\RoomManager;
use App\Livewire\Admin\RoomTypeManager;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Room types and physical rooms are protected by restricting foreign keys once
 * they appear in a booking. Without a check of their own the delete button
 * hands the admin a 500 page; the operation they actually want is to stop
 * selling the thing, so the refusal has to say that.
 */
class CatalogDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_a_room_type_that_was_never_booked_can_be_deleted(): void
    {
        $roomType = RoomType::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(RoomTypeManager::class)
            ->call('delete', $roomType->id);

        $this->assertDatabaseMissing('room_types', ['id' => $roomType->id]);
    }

    public function test_a_booked_room_type_is_refused_instead_of_erroring(): void
    {
        $roomType = RoomType::factory()->create(['name' => 'Deluxe Garden']);
        $this->bookingFor($roomType);

        Livewire::actingAs($this->admin)
            ->test(RoomTypeManager::class)
            ->call('delete', $roomType->id);

        $this->assertDatabaseHas('room_types', ['id' => $roomType->id]);
        $this->assertStringContainsString('Deluxe Garden', session('error'));
    }

    public function test_an_unused_room_can_be_deleted(): void
    {
        $room = Room::factory()->create(['room_type_id' => RoomType::factory()->create()->id]);

        Livewire::actingAs($this->admin)
            ->test(RoomManager::class)
            ->call('delete', $room->id);

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    public function test_a_room_that_hosted_a_guest_is_refused_instead_of_erroring(): void
    {
        $roomType = RoomType::factory()->create();
        $room = Room::factory()->create(['room_type_id' => $roomType->id, 'room_number' => '101']);
        $booking = $this->bookingFor($roomType);

        RoomAssignment::create([
            'booking_item_id' => $booking->items->first()->id,
            'room_id' => $room->id,
            'stay_from' => $booking->check_in_date,
            'stay_to' => $booking->check_out_date,
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(RoomManager::class)
            ->call('delete', $room->id);

        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
        $this->assertStringContainsString('101', session('error'));
    }

    private function bookingFor(RoomType $roomType): Booking
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
            'held_until' => null,
            'confirmed_at' => now(),
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'room_type_id' => $roomType->id,
            'room_type_name' => $roomType->name,
            'rooms' => 1,
            'subtotal_amount' => 1_000_000,
        ]);

        return $booking->fresh('items');
    }
}
