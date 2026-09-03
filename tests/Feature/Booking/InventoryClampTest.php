<?php

namespace Tests\Feature\Booking;

use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Services\Booking\BookingInventoryService;
use App\Support\StayPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The inventory counters are UNSIGNED columns. Subtracting past zero must clamp,
 * not explode — the clamp exists precisely for the case where state has drifted,
 * so it has to survive being handed a value it cannot satisfy.
 */
class InventoryClampTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $roomType;

    private StayPeriod $stay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roomType = RoomType::factory()->create(['default_inventory' => 3]);
        $this->stay = new StayPeriod(today()->addDays(4)->toDateString(), today()->addDays(6)->toDateString());
    }

    private function seedRow(int $held = 0, int $confirmed = 0): void
    {
        foreach ($this->stay->stayDateStrings() as $date) {
            RoomTypeInventory::create([
                'room_type_id' => $this->roomType->id,
                'inventory_date' => $date,
                'total_inventory' => 3,
                'blocked_inventory' => 0,
                'held_inventory' => $held,
                'confirmed_inventory' => $confirmed,
            ]);
        }
    }

    private function rows(): Collection
    {
        return RoomTypeInventory::query()
            ->where('room_type_id', $this->roomType->id)
            ->whereIn('inventory_date', $this->stay->stayDateStrings())
            ->get();
    }

    public function test_releasing_more_than_is_held_clamps_to_zero_instead_of_underflowing(): void
    {
        $this->seedRow(held: 1);
        $service = app(BookingInventoryService::class);

        DB::transaction(function () use ($service) {
            $service->releaseHeld($service->lockRows($this->roomType, $this->stay), 5);
        });

        foreach ($this->rows() as $row) {
            $this->assertSame(0, $row->held_inventory);
        }
    }

    public function test_releasing_confirmed_past_zero_clamps_to_zero(): void
    {
        $this->seedRow(confirmed: 2);
        $service = app(BookingInventoryService::class);

        DB::transaction(function () use ($service) {
            $service->releaseConfirmed($service->lockRows($this->roomType, $this->stay), 9);
        });

        foreach ($this->rows() as $row) {
            $this->assertSame(0, $row->confirmed_inventory);
        }
    }

    /**
     * The exact shape that broke a pay-at-hotel reservation: converting a hold
     * on a row whose held counter had already drifted to zero.
     */
    public function test_converting_a_hold_that_is_no_longer_recorded_does_not_error(): void
    {
        $this->seedRow(held: 0);
        $service = app(BookingInventoryService::class);

        DB::transaction(function () use ($service) {
            $service->convertHeldToConfirmed($service->lockRows($this->roomType, $this->stay), 1);
        });

        foreach ($this->rows() as $row) {
            $this->assertSame(0, $row->held_inventory);
            $this->assertSame(1, $row->confirmed_inventory);
        }
    }

    public function test_a_normal_conversion_still_moves_the_right_number_of_rooms(): void
    {
        $this->seedRow(held: 2);
        $service = app(BookingInventoryService::class);

        DB::transaction(function () use ($service) {
            $service->convertHeldToConfirmed($service->lockRows($this->roomType, $this->stay), 2);
        });

        foreach ($this->rows() as $row) {
            $this->assertSame(0, $row->held_inventory);
            $this->assertSame(2, $row->confirmed_inventory);
        }
    }
}
