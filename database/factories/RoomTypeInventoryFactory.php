<?php

namespace Database\Factories;

use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomTypeInventory>
 */
class RoomTypeInventoryFactory extends Factory
{
    protected $model = RoomTypeInventory::class;

    public function definition(): array
    {
        return [
            'room_type_id' => RoomType::factory(),
            'inventory_date' => now()->toDateString(),
            'total_inventory' => 5,
            'blocked_inventory' => 0,
            'held_inventory' => 0,
            'confirmed_inventory' => 0,
        ];
    }
}
