<?php

namespace App\Support\Pricing;

use App\Models\Promotion;

/**
 * The server-authoritative price breakdown for a booking. Never reconstructed
 * from client input. All amounts are integer rupiah.
 */
final class PriceQuote
{
    /**
     * @param  array<int, NightPrice>  $nights
     */
    public function __construct(
        public readonly array $nights,
        public readonly int $rooms,
        public readonly int $nightsCount,
        public readonly int $subtotalBeforeDiscount,
        public readonly int $discountTotal,
        public readonly int $subtotalAfterDiscount,
        public readonly int $taxTotal,
        public readonly int $serviceTotal,
        public readonly int $grandTotal,
        public readonly ?Promotion $promotion = null,
        public readonly string $currency = 'IDR',
    ) {}

    public function hasPromotion(): bool
    {
        return $this->promotion !== null && $this->discountTotal > 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function nightSnapshots(): array
    {
        return array_map(fn (NightPrice $n) => $n->toSnapshot(), $this->nights);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rooms' => $this->rooms,
            'nights' => $this->nightsCount,
            'subtotal_before_discount' => $this->subtotalBeforeDiscount,
            'discount_total' => $this->discountTotal,
            'subtotal_after_discount' => $this->subtotalAfterDiscount,
            'tax_total' => $this->taxTotal,
            'service_total' => $this->serviceTotal,
            'grand_total' => $this->grandTotal,
            'promotion' => $this->promotion?->only(['id', 'code', 'name']),
            'currency' => $this->currency,
        ];
    }
}
