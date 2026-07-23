<?php

namespace Tests\Feature\Booking;

use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Services\Booking\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): InventoryService
    {
        return app(InventoryService::class);
    }

    public function test_ensure_rows_creates_missing_dates_from_default_inventory(): void
    {
        $roomType = RoomType::factory()->create(['default_inventory' => 4]);

        $rows = $this->service()->ensureRows($roomType, ['2026-09-01', '2026-09-02']);

        $this->assertCount(2, $rows);
        $this->assertDatabaseHas('room_type_inventories', [
            'room_type_id' => $roomType->id,
            'inventory_date' => '2026-09-01',
            'total_inventory' => 4,
        ]);
    }

    public function test_total_cannot_drop_below_confirmed(): void
    {
        $roomType = RoomType::factory()->create(['default_inventory' => 5]);
        $row = RoomTypeInventory::create([
            'room_type_id' => $roomType->id, 'inventory_date' => '2026-09-01',
            'total_inventory' => 5, 'confirmed_inventory' => 3, 'held_inventory' => 0, 'blocked_inventory' => 0,
        ]);

        $this->expectException(RuntimeException::class);
        $this->service()->setTotal($row, 2);
    }

    public function test_blocked_cannot_exceed_available_units(): void
    {
        $roomType = RoomType::factory()->create(['default_inventory' => 5]);
        $row = RoomTypeInventory::create([
            'room_type_id' => $roomType->id, 'inventory_date' => '2026-09-01',
            'total_inventory' => 5, 'confirmed_inventory' => 2, 'held_inventory' => 1, 'blocked_inventory' => 0,
        ]);

        // Only 2 units are free to block (5 - 2 - 1).
        $this->expectException(RuntimeException::class);
        $this->service()->setBlocked($row, 3);
    }

    public function test_blocking_reduces_availability_to_zero(): void
    {
        $roomType = RoomType::factory()->create(['default_inventory' => 3]);
        $row = RoomTypeInventory::create([
            'room_type_id' => $roomType->id, 'inventory_date' => '2026-09-01',
            'total_inventory' => 3, 'confirmed_inventory' => 0, 'held_inventory' => 0, 'blocked_inventory' => 0,
        ]);

        $this->service()->setBlocked($row, 3);

        $this->assertSame(0, $row->fresh()->available());
    }

    public function test_bulk_update_applies_price_and_total_across_range(): void
    {
        $roomType = RoomType::factory()->create(['default_inventory' => 2, 'base_price' => 500_000]);

        // 1 Sep -> 4 Sep is 3 nights (1, 2, 3).
        $count = $this->service()->bulkUpdate($roomType, '2026-09-01', '2026-09-04', ['total' => 6, 'price' => 900_000]);

        $this->assertSame(3, $count);
        $this->assertDatabaseHas('room_type_inventories', ['inventory_date' => '2026-09-03', 'total_inventory' => 6]);
        $this->assertDatabaseHas('rate_plans', ['rate_date' => '2026-09-03', 'price' => 900_000]);
        // The checkout night is untouched.
        $this->assertDatabaseMissing('room_type_inventories', ['inventory_date' => '2026-09-04']);
    }
}
