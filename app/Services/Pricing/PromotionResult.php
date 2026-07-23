<?php

namespace App\Services\Pricing;

use App\Models\Promotion;

final class PromotionResult
{
    public function __construct(
        public readonly ?Promotion $promotion,
        public readonly int $discount,
        public readonly ?string $error = null,
    ) {}

    public static function none(): self
    {
        return new self(null, 0, null);
    }

    public static function invalid(string $error): self
    {
        return new self(null, 0, $error);
    }

    public function applied(): bool
    {
        return $this->promotion !== null && $this->discount > 0;
    }
}
