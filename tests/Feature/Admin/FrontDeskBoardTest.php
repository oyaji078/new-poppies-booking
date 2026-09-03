<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Admin\FrontDesk;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The front desk is a single board — the arrival/in-house/departure tabs were
 * removed. A booking's stage is carried by the card colour instead: white
 * before check-in, green while in house, blue once checked out.
 */
class FrontDeskBoardTest extends TestCase
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

    private function booking(BookingStatus $status): Booking
    {
        $booking = Booking::factory()->create([
            'status' => $status,
            'payment_status' => PaymentStatus::PAID,
            'check_in_date' => today()->toDateString(),
            'check_out_date' => today()->addDays(2)->toDateString(),
            'rooms' => 1,
            'held_until' => null,
            'confirmed_at' => now(),
            'checked_in_at' => $status === BookingStatus::CONFIRMED ? null : now(),
            'checked_out_at' => $status === BookingStatus::CHECKED_OUT ? now() : null,
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'room_type_id' => $this->roomType->id,
            'room_type_name' => $this->roomType->name,
            'rooms' => 1,
            'subtotal_amount' => 1_000_000,
        ]);

        return $booking->fresh('items');
    }

    public function test_the_removed_tabs_are_gone_and_every_stage_shares_one_board(): void
    {
        $arriving = $this->booking(BookingStatus::CONFIRMED);
        $inHouse = $this->booking(BookingStatus::CHECKED_IN);
        $departed = $this->booking(BookingStatus::CHECKED_OUT);

        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->assertDontSee('Sedang Menginap')
            ->assertDontSee('Keberangkatan')
            ->assertSee($arriving->code)
            ->assertSee($inHouse->code)
            ->assertSee($departed->code);
    }

    public function test_a_card_starts_white_turns_green_after_check_in_and_blue_after_check_out(): void
    {
        $room = Room::factory()->create(['room_type_id' => $this->roomType->id, 'room_number' => 'STD-201']);
        $booking = $this->booking(BookingStatus::CONFIRMED);
        $item = $booking->items->first();

        $component = Livewire::actingAs($this->admin)->test(FrontDesk::class);

        // Before any action the card is white.
        $component->assertSee('bg-white', escape: false)
            ->assertSee('Belum check-in');

        // Check-in turns it green.
        $component->call('openCheckIn', $booking->id)
            ->set("selectedRooms.{$item->id}", [$room->id])
            ->call('submitCheckIn')
            ->assertHasNoErrors()
            ->assertSee('bg-emerald-50', escape: false)
            ->assertSee('Sudah check-in');

        $this->assertSame(BookingStatus::CHECKED_IN, $booking->fresh()->status);

        // Check-out turns it blue and the booking stays on the board today.
        $component->call('openCheckOut', $booking->id)
            ->call('submitCheckOut')
            ->assertHasNoErrors()
            ->assertSee('bg-sky-50', escape: false)
            ->assertSee('Sudah check-out');

        $this->assertSame(BookingStatus::CHECKED_OUT, $booking->fresh()->status);
    }

    public function test_the_status_chips_filter_the_board(): void
    {
        $arriving = $this->booking(BookingStatus::CONFIRMED);
        $inHouse = $this->booking(BookingStatus::CHECKED_IN);
        $departed = $this->booking(BookingStatus::CHECKED_OUT);

        $component = Livewire::actingAs($this->admin)->test(FrontDesk::class);

        // No filter: everything on the board.
        $component->assertSee($arriving->code)->assertSee($inHouse->code)->assertSee($departed->code);

        $component->call('setFilter', BookingStatus::CHECKED_IN->value)
            ->assertSee($inHouse->code)
            ->assertDontSee($arriving->code)
            ->assertDontSee($departed->code);

        $component->call('setFilter', BookingStatus::CONFIRMED->value)
            ->assertSee($arriving->code)
            ->assertDontSee($inHouse->code);
    }

    public function test_clicking_the_active_chip_again_clears_the_filter(): void
    {
        $arriving = $this->booking(BookingStatus::CONFIRMED);
        $inHouse = $this->booking(BookingStatus::CHECKED_IN);

        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('setFilter', BookingStatus::CONFIRMED->value)
            ->assertSet('filter', BookingStatus::CONFIRMED->value)
            ->assertDontSee($inHouse->code)
            ->call('setFilter', BookingStatus::CONFIRMED->value)
            ->assertSet('filter', '')
            ->assertSee($arriving->code)
            ->assertSee($inHouse->code);
    }

    /**
     * The chips carry counts, and those counts must describe the whole board —
     * otherwise filtering would make every other chip read zero.
     */
    public function test_the_counts_stay_whole_board_while_a_filter_is_active(): void
    {
        $this->booking(BookingStatus::CONFIRMED);
        $this->booking(BookingStatus::CONFIRMED);
        $this->booking(BookingStatus::CHECKED_IN);

        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('setFilter', BookingStatus::CHECKED_IN->value)
            ->assertViewHas('totalCount', 3)
            ->assertViewHas('counts', fn ($counts) => $counts[BookingStatus::CONFIRMED->value] === 2
                && $counts[BookingStatus::CHECKED_IN->value] === 1);
    }

    public function test_an_empty_filtered_board_offers_a_way_back(): void
    {
        $this->booking(BookingStatus::CONFIRMED);

        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->call('setFilter', BookingStatus::NO_SHOW->value)
            ->assertSee('Tampilkan semua');
    }

    public function test_action_buttons_follow_the_stage(): void
    {
        $this->booking(BookingStatus::CONFIRMED);

        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->assertSee('Check-in')
            ->assertSee('Tidak Hadir');

        Booking::query()->delete();
        $this->booking(BookingStatus::CHECKED_IN);

        Livewire::actingAs($this->admin)
            ->test(FrontDesk::class)
            ->assertSee('Check-out')
            ->assertDontSee('Tidak Hadir');
    }
}
