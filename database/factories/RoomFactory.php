<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'room_type_id' => RoomType::factory(),
            'room_number' => strtoupper(fake()->unique()->bothify('R-###')),
            'floor' => (string) fake()->numberBetween(1, 3),
            'is_active' => true,
            'under_maintenance' => false,
        ];
    }

    public function maintenance(): static
    {
        return $this->state(fn () => ['under_maintenance' => true]);
    }
}
