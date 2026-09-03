<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Admin\FrontDesk;
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
 * Picking the physical room at check-in.
 *
 * Livewire only binds a group of checkboxes sharing one wire:model as an ARRAY
 * when that property already holds an array. Left unseeded it becomes a single
 * boolean, which makes every box in the group tick together and then explodes in
 * array_filter(). These tests pin both halves of that down.
 */
class FrontDeskRoomPickerTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $roomType;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->roomType = RoomType::factory()->create(['default_inventory' => 4]);
    }

    private function arrival(int $rooms = 1): Booking
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
            'check_in_date' => today()->toDateString(),
            'check_out_date' => today()->addDays(2)->toDateString(),
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

    public function test_opening_check_in_seeds_an_empty_array_per_booking_item(): void
    {
        Room::factory()->count(3)->create(['room_type_id' => $this->roomType->id]);
        $booking = $this->arrival();
        $item = $booking->items->first();

        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('openCheckIn', $booking->id)
            ->assertSet("selectedRooms.{$item->id}", []);
    }

    public function test_selecting_one_room_assigns_only_that_room(): void
    {
        $rooms = Room::factory()->count(3)->sequence(
            ['room_number' => 'STD-301'],
            ['room_number' => 'STD-302'],
            ['room_number' => 'STD-303'],
        )->create(['room_type_id' => $this->roomType->id]);

        $booking = $this->arrival();
        $item = $booking->items->first();

        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('openCheckIn', $booking->id)
            ->set("selectedRooms.{$item->id}", [$rooms[1]->id])
            ->call('submitCheckIn')
            ->assertHasNoErrors();

        $this->assertSame(BookingStatus::CHECKED_IN, $booking->fresh()->status);

        $assigned = RoomAssignment::where('booking_item_id', $item->id)->get();
        $this->assertCount(1, $assigned, 'Exactly one physical room should be assigned.');
        $this->assertSame($rooms[1]->id, $assigned->first()->room_id);
    }

    /**
     * The crash the admin actually hit: a boolean arriving where an array of
     * room ids was expected must surface as a validation message, not a 500.
     */
    public function test_a_scalar_selection_is_rejected_instead_of_throwing(): void
    {
        Room::factory()->create(['room_type_id' => $this->roomType->id]);
        $booking = $this->arrival();
        $item = $booking->items->first();

        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('openCheckIn', $booking->id)
            ->set("selectedRooms.{$item->id}", true)
            ->call('submitCheckIn')
            ->assertHasErrors('selectedRooms');

        $this->assertSame(BookingStatus::CONFIRMED, $booking->fresh()->status);
    }

    public function test_choosing_no_room_is_rejected(): void
    {
        Room::factory()->create(['room_type_id' => $this->roomType->id]);
        $booking = $this->arrival();

        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('openCheckIn', $booking->id)
            ->call('submitCheckIn')
            ->assertHasErrors('selectedRooms');

        $this->assertSame(BookingStatus::CONFIRMED, $booking->fresh()->status);
    }

    public function test_a_two_room_booking_needs_exactly_two_physical_rooms(): void
    {
        $rooms = Room::factory()->count(3)->create(['room_type_id' => $this->roomType->id]);
        $booking = $this->arrival(rooms: 2);
        $item = $booking->items->first();

        $component = Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('openCheckIn', $booking->id);

        // One room for a two-room booking is not enough.
        $component->set("selectedRooms.{$item->id}", [$rooms[0]->id])
            ->call('submitCheckIn')
            ->assertHasErrors('selectedRooms');

        $component->set("selectedRooms.{$item->id}", [$rooms[0]->id, $rooms[1]->id])
            ->call('submitCheckIn')
            ->assertHasNoErrors();

        $this->assertCount(2, RoomAssignment::where('booking_item_id', $item->id)->get());
    }

    public function test_auto_assign_picks_exactly_the_booked_number_of_distinct_rooms(): void
    {
        Room::factory()->count(4)->create(['room_type_id' => $this->roomType->id]);
        $booking = $this->arrival(rooms: 2);
        $item = $booking->items->first();

        $component = Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('openCheckIn', $booking->id)
            ->call('autoAssignRooms');

        $picked = $component->get("selectedRooms.{$item->id}");

        $this->assertCount(2, $picked);
        $this->assertSame($picked, array_unique($picked), 'The same room must not be picked twice.');

        $component->call('submitCheckIn')->assertHasNoErrors();
        $this->assertCount(2, RoomAssignment::where('booking_item_id', $item->id)->get());
    }

    public function test_auto_assign_never_offers_a_room_that_is_already_occupied(): void
    {
        $rooms = Room::factory()->count(2)->sequence(
            ['room_number' => 'STD-601'],
            ['room_number' => 'STD-602'],
        )->create(['room_type_id' => $this->roomType->id]);

        $first = $this->arrival();
        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('openCheckIn', $first->id)
            ->set("selectedRooms.{$first->items->first()->id}", [$rooms[0]->id])
            ->call('submitCheckIn')
            ->assertHasNoErrors();

        $second = $this->arrival();

        $component = Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('openCheckIn', $second->id)
            ->call('autoAssignRooms');

        $this->assertSame(
            [(string) $rooms[1]->id],
            $component->get("selectedRooms.{$second->items->first()->id}"),
        );
    }

    public function test_auto_assign_falls_short_when_the_hotel_lacks_free_rooms(): void
    {
        // Two rooms booked, only one physical room exists.
        Room::factory()->create(['room_type_id' => $this->roomType->id]);
        $booking = $this->arrival(rooms: 2);
        $item = $booking->items->first();

        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('openCheckIn', $booking->id)
            ->call('autoAssignRooms')
            // Warned before submitting, and the service still refuses.
            ->assertSee('Hanya 1 kamar fisik yang kosong')
            ->call('submitCheckIn')
            ->assertHasErrors('selectedRooms');

        $this->assertSame(BookingStatus::CONFIRMED, $booking->fresh()->status);
        $this->assertCount(0, RoomAssignment::where('booking_item_id', $item->id)->get());
    }

    public function test_the_same_room_twice_does_not_satisfy_a_two_room_booking(): void
    {
        $rooms = Room::factory()->count(2)->create(['room_type_id' => $this->roomType->id]);
        $booking = $this->arrival(rooms: 2);
        $item = $booking->items->first();

        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('openCheckIn', $booking->id)
            ->set("selectedRooms.{$item->id}", [$rooms[0]->id, $rooms[0]->id])
            ->call('submitCheckIn')
            ->assertHasErrors('selectedRooms');

        $this->assertCount(0, RoomAssignment::where('booking_item_id', $item->id)->get());
    }

    /**
     * The visible half of the bug: every box used to tick at once. Each room now
     * carries its own state, and once enough are chosen the rest lock.
     */
    public function test_the_picker_counts_the_selection_and_locks_the_rest_when_full(): void
    {
        $rooms = Room::factory()->count(3)->sequence(
            ['room_number' => 'STD-501'],
            ['room_number' => 'STD-502'],
            ['room_number' => 'STD-503'],
        )->create(['room_type_id' => $this->roomType->id]);

        $booking = $this->arrival();
        $item = $booking->items->first();

        $component = Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('openCheckIn', $booking->id);

        $component->assertSee('Dipilih 0 dari 1 kamar fisik');
        // Three separate boxes, one per physical room — not one shared toggle.
        $this->assertSame(3, $this->checkboxCount($component->html()));
        $this->assertSame(0, $this->lockedCount($component->html()));

        $component->set("selectedRooms.{$item->id}", [$rooms[0]->id]);

        $component->assertSee('Dipilih 1 dari 1 kamar fisik');
        // The two rooms not chosen are now locked, so the count cannot exceed 1.
        $this->assertSame(2, $this->lockedCount($component->html()));
    }

    /** @return array<int, string> the room checkbox inputs only */
    private function checkboxes(string $html): array
    {
        preg_match_all('/<input type="checkbox"[^>]*selectedRooms[^>]*>/', $html, $matches);

        return $matches[0];
    }

    private function checkboxCount(string $html): int
    {
        return count($this->checkboxes($html));
    }

    /**
     * Counts the real disabled ATTRIBUTE. Matching the bare word would also hit
     * the `disabled:opacity-50` Tailwind variant in the class list, so require
     * the attribute boundary.
     */
    private function lockedCount(string $html): int
    {
        return count(array_filter(
            $this->checkboxes($html),
            fn (string $input) => preg_match('/\sdisabled(\s|>|=)/', $input) === 1,
        ));
    }

    public function test_a_room_already_occupied_is_not_offered(): void
    {
        $rooms = Room::factory()->count(2)->sequence(
            ['room_number' => 'STD-401'],
            ['room_number' => 'STD-402'],
        )->create(['room_type_id' => $this->roomType->id]);

        // STD-401 is taken by an overlapping stay that has not checked out.
        $occupied = $this->arrival();
        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('openCheckIn', $occupied->id)
            ->set("selectedRooms.{$occupied->items->first()->id}", [$rooms[0]->id])
            ->call('submitCheckIn')
            ->assertHasNoErrors();

        $next = $this->arrival();

        // Assert on the offered set, not the rendered page: the board itself
        // legitimately prints STD-401 on the card of the guest occupying it.
        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('openCheckIn', $next->id)
            ->assertViewHas('availableRooms', function (array $offered) use ($next) {
                $numbers = $offered[$next->items->first()->id]->pluck('room_number')->all();

                return $numbers === ['STD-402'];
            });
    }
}
