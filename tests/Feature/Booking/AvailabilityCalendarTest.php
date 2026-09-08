<?php

namespace Tests\Feature\Booking;

use App\Livewire\Public\AvailabilityCalendar;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Services\Booking\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public date picker. Its whole job is to be honest about which nights are
 * already reserved, so the rules that decide "full" have to hold exactly.
 */
class AvailabilityCalendarTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roomType = RoomType::factory()->create([
            'default_inventory' => 2,
            'is_published' => true,
        ]);
        Room::factory()->count(2)->create(['room_type_id' => $this->roomType->id]);
    }

    private function book(string $date, int $confirmed): void
    {
        RoomTypeInventory::updateOrCreate(
            ['room_type_id' => $this->roomType->id, 'inventory_date' => $date],
            ['total_inventory' => 2, 'blocked_inventory' => 0, 'held_inventory' => 0, 'confirmed_inventory' => $confirmed],
        );
    }

    private function day(int $offset): string
    {
        return CarbonImmutable::today()->addDays($offset)->toDateString();
    }

    public function test_dates_with_no_inventory_row_count_as_fully_available(): void
    {
        $availability = app(AvailabilityService::class)
            ->dailyAvailability($this->roomType, $this->day(1), $this->day(3));

        $this->assertSame([
            $this->day(1) => 2,
            $this->day(2) => 2,
            $this->day(3) => 2,
        ], $availability);
    }

    public function test_reserved_rooms_reduce_the_count_and_a_sold_out_night_reads_zero(): void
    {
        $this->book($this->day(1), confirmed: 1);
        $this->book($this->day(2), confirmed: 2);

        $availability = app(AvailabilityService::class)
            ->dailyAvailability($this->roomType, $this->day(1), $this->day(2));

        $this->assertSame(1, $availability[$this->day(1)]);
        $this->assertSame(0, $availability[$this->day(2)]);
    }

    public function test_availability_is_capped_by_physical_rooms_under_maintenance(): void
    {
        Room::query()->where('room_type_id', $this->roomType->id)->limit(1)->update(['under_maintenance' => true]);

        $availability = app(AvailabilityService::class)
            ->dailyAvailability($this->roomType, $this->day(1), $this->day(1));

        $this->assertSame(1, $availability[$this->day(1)]);
    }

    public function test_a_sold_out_night_cannot_start_a_stay(): void
    {
        $this->book($this->day(3), confirmed: 2);

        Livewire::test(AvailabilityCalendar::class, ['roomType' => $this->roomType])
            ->call('selectDate', $this->day(3))
            ->assertSet('checkIn', '');
    }

    public function test_picking_check_in_then_check_out_builds_the_range(): void
    {
        Livewire::test(AvailabilityCalendar::class, ['roomType' => $this->roomType])
            ->call('selectDate', $this->day(2))
            ->assertSet('checkIn', $this->day(2))
            ->assertSet('checkOut', '')
            ->call('selectDate', $this->day(5))
            ->assertSet('checkOut', $this->day(5));
    }

    /**
     * The checkout day is not a stay night, so a fully booked date is still a
     * legal date to leave on — it just cannot be slept through.
     */
    public function test_a_sold_out_night_may_be_the_check_out_date(): void
    {
        $this->book($this->day(4), confirmed: 2);

        Livewire::test(AvailabilityCalendar::class, ['roomType' => $this->roomType])
            ->call('selectDate', $this->day(2))
            ->call('selectDate', $this->day(4))
            ->assertSet('checkOut', $this->day(4));
    }

    public function test_a_stay_cannot_span_past_a_sold_out_night(): void
    {
        $this->book($this->day(4), confirmed: 2);

        Livewire::test(AvailabilityCalendar::class, ['roomType' => $this->roomType])
            ->call('selectDate', $this->day(2))
            // Night 4 is full, so leaving on day 5 would require sleeping through it.
            ->call('selectDate', $this->day(5))
            ->assertSet('checkOut', '');
    }

    public function test_asking_for_more_rooms_than_are_free_marks_the_night_full(): void
    {
        $this->book($this->day(2), confirmed: 1); // 1 of 2 left

        Livewire::test(AvailabilityCalendar::class, ['roomType' => $this->roomType])
            ->set('rooms', 2)
            ->call('selectDate', $this->day(2))
            ->assertSet('checkIn', '');
    }

    public function test_a_zero_room_party_cannot_unlock_a_sold_out_night(): void
    {
        // The number input's min/max is browser-side only. Every "is this night
        // bookable" test compares free rooms against $rooms, so an unclamped
        // zero would paint a sold-out night as available.
        $this->book($this->day(2), confirmed: 2); // sold out

        Livewire::test(AvailabilityCalendar::class, ['roomType' => $this->roomType])
            ->set('rooms', 0)
            ->assertSet('rooms', 1)
            ->call('selectDate', $this->day(2))
            ->assertSet('checkIn', '');
    }

    public function test_the_party_size_is_clamped_to_the_allowed_range(): void
    {
        Livewire::test(AvailabilityCalendar::class, ['roomType' => $this->roomType])
            ->set('rooms', 999)
            ->set('adults', -4)
            ->set('children', -1)
            ->assertSet('rooms', 10)
            ->assertSet('adults', 1)
            ->assertSet('children', 0);
    }

    public function test_clicking_an_earlier_date_restarts_the_selection(): void
    {
        Livewire::test(AvailabilityCalendar::class, ['roomType' => $this->roomType])
            ->call('selectDate', $this->day(5))
            ->call('selectDate', $this->day(2))
            ->assertSet('checkIn', $this->day(2))
            ->assertSet('checkOut', '');
    }

    public function test_past_dates_are_never_selectable(): void
    {
        Livewire::test(AvailabilityCalendar::class, ['roomType' => $this->roomType])
            ->call('selectDate', $this->day(-3))
            ->assertSet('checkIn', '');
    }

    public function test_the_calendar_cannot_page_back_before_the_current_month(): void
    {
        $thisMonth = CarbonImmutable::today()->startOfMonth()->toDateString();

        Livewire::test(AvailabilityCalendar::class, ['roomType' => $this->roomType])
            ->call('previousMonth')
            ->assertSet('month', $thisMonth)
            ->call('nextMonth')
            ->assertSet('month', CarbonImmutable::today()->startOfMonth()->addMonth()->toDateString())
            ->call('previousMonth')
            ->assertSet('month', $thisMonth);
    }

    public function test_the_calendar_renders_on_the_room_detail_page_with_a_booking_link(): void
    {
        $this->get(route('rooms.show', $this->roomType->slug))
            ->assertOk()
            ->assertSee('Pilih Tanggal Menginap')
            ->assertSeeLivewire(AvailabilityCalendar::class);
    }
}
