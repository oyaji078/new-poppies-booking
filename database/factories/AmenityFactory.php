<?php

namespace Database\Factories;

use App\Models\Amenity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Amenity>
 */
class AmenityFactory extends Factory
{
    protected $model = Amenity::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Wi-Fi', 'AC', 'TV', 'Minibar', 'Balcony', 'Safe Box', 'Hot Water', 'Coffee Maker']);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'icon' => null,
            'category' => 'general',
        ];
    }
}
