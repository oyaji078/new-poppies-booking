<?php

namespace Database\Factories;

use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    protected $model = RoomType::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Standard Room', 'Deluxe Room', 'Family Room', 'Ocean Suite', 'Garden Bungalow']);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'short_description' => fake()->sentence(10),
            'full_description' => fake()->paragraphs(3, true),
            'adult_capacity' => 2,
            'child_capacity' => 1,
            'max_guests' => 3,
            'bed_type' => fake()->randomElement(['1 King Bed', '2 Twin Beds', '1 Queen Bed']),
            'room_size' => fake()->numberBetween(24, 60),
            'base_price' => fake()->numberBetween(400_000, 2_000_000),
            'policies' => 'Check-in 14.00, check-out 12.00. Dilarang merokok di dalam kamar.',
            'default_inventory' => fake()->numberBetween(3, 10),
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
