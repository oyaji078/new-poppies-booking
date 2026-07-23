<?php

namespace Tests\Feature\Booking;

use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Services\Booking\AvailabilityService;
use App\Support\StayPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AvailabilityService
    {
        return app(AvailabilityService::class);
    }

    private function roomTypeWithRooms(int $count): RoomType
    {
        $roomType = RoomType::factory()->create(['default_inventory' => $count, 'max_guests' => 4]);
        Room::factory()->count($count)->create(['room_type_id' => $roomType->id]);

        return $roomType;
    }

    public function test_available_units_is_min_across_all_stay_nights(): void
    {
        $roomType = $this->roomTypeWithRooms(5);
        $stay = new StayPeriod('2026-08-10', '2026-08-13'); // 10,11,12

        foreach (['2026-08-10' => 5, '2026-08-11' => 2, '2026-08-12' => 4] as $date => $total) {
            RoomTypeInventory::create([
                'room_type_id' => $roomType->id, 'inventory_date' => $date,
                'total_inventory' => $total, 'held_inventory' => 0, 'confirmed_inventory' => 0, 'blocked_inventory' => 0,
            ]);
        }

        // Bottleneck night is 11 Aug with 2 available.
        $this->assertSame(2, $this->service()->availableUnits($roomType, $stay));
        $this->assertTrue($this->service()->isAvailable($roomType, $stay, 2));
        $this->assertFalse($this->service()->isAvailable($roomType, $stay, 3));
    }

    public function test_zero_availability_when_one_night_is_full(): void
    {
        $roomType = $this->roomTypeWithRooms(3);
        $stay = new StayPeriod('2026-08-10', '2026-08-12'); // 10, 11

        RoomTypeInventory::create([
            'room_type_id' => $roomType->id, 'inventory_date' => '2026-08-10',
            'total_inventory' => 3, 'held_inventory' => 0, 'confirmed_inventory' => 0, 'blocked_inventory' => 0,
        ]);
        RoomTypeInventory::create([
            'room_type_id' => $roomType->id, 'inventory_date' => '2026-08-11',
            'total_inventory' => 3, 'held_inventory' => 1, 'confirmed_inventory' => 2, 'blocked_inventory' => 0,
        ]);

        $this->assertSame(0, $this->service()->availableUnits($roomType, $stay));
        $this->assertFalse($this->service()->isAvailable($roomType, $stay, 1));
    }

    public function test_maintenance_rooms_reduce_availability(): void
    {
        $roomType = RoomType::factory()->create(['default_inventory' => 5, 'max_guests' => 4]);
        Room::factory()->count(2)->create(['room_type_id' => $roomType->id]);              // sellable
        Room::factory()->count(3)->maintenance()->create(['room_type_id' => $roomType->id]); // not sellable

        $stay = new StayPeriod('2026-08-10', '2026-08-11');
        RoomTypeInventory::create([
            'room_type_id' => $roomType->id, 'inventory_date' => '2026-08-10',
            'total_inventory' => 5, 'held_inventory' => 0, 'confirmed_inventory' => 0, 'blocked_inventory' => 0,
        ]);

        // Inventory says 5 but only 2 rooms are sellable.
        $this->assertSame(2, $this->service()->availableUnits($roomType, $stay));
    }

    public function test_search_filters_by_capacity_and_availability(): void
    {
        $small = $this->roomTypeWithRooms(2);
        $small->update(['max_guests' => 2, 'name' => 'Small']);
        $big = $this->roomTypeWithRooms(2);
        $big->update(['max_guests' => 6, 'name' => 'Big']);

        $stay = new StayPeriod('2026-08-10', '2026-08-11');
        foreach ([$small, $big] as $rt) {
            RoomTypeInventory::create([
                'room_type_id' => $rt->id, 'inventory_date' => '2026-08-10',
                'total_inventory' => 2, 'held_inventory' => 0, 'confirmed_inventory' => 0, 'blocked_inventory' => 0,
            ]);
        }

        // Party of 5 in 1 room => only the big room type fits.
        $results = $this->service()->search($stay, adults: 5, children: 0, rooms: 1);

        $this->assertCount(1, $results);
        $this->assertSame('Big', $results->first()['room_type']->name);
    }
}
