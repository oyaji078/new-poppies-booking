<?php

namespace Database\Factories;

use App\Models\GalleryImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GalleryImage>
 */
class GalleryImageFactory extends Factory
{
    protected $model = GalleryImage::class;

    public function definition(): array
    {
        return [
            'path' => 'gallery-images/'.Str::uuid()->toString().'.jpg',
            'original_name' => 'foto.jpg',
            'title' => $this->faker->words(2, true),
            'alt' => null,
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function hidden(): self
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
