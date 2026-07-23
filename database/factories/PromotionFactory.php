<?php

namespace Database\Factories;

use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'name' => 'Promo '.fake()->word(),
            'code' => strtoupper(Str::random(8)),
            'type' => PromotionType::PERCENTAGE,
            'value' => 10,
            'max_discount' => null,
            'is_automatic' => false,
            'min_nights' => 1,
            'min_transaction' => 0,
            'usage_limit' => null,
            'used_count' => 0,
            'is_active' => true,
        ];
    }

    public function percentage(int $percent, ?int $maxDiscount = null): static
    {
        return $this->state(fn () => [
            'type' => PromotionType::PERCENTAGE,
            'value' => $percent,
            'max_discount' => $maxDiscount,
        ]);
    }

    public function fixed(int $amount): static
    {
        return $this->state(fn () => ['type' => PromotionType::FIXED, 'value' => $amount]);
    }

    public function automatic(): static
    {
        return $this->state(fn () => ['is_automatic' => true, 'code' => null]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
