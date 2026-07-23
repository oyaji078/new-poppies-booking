<?php

namespace App\Support\Pricing;

/**
 * A single night's price snapshot. All amounts are integer rupiah and cover the
 * whole booking (all rooms) for that night. `tax` is the combined tax + service
 * share for the night; the split is preserved in `metadata`.
 *
 * Invariant: final === base + adjustment - discount + tax
 */
final class NightPrice
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $date,
        public readonly int $base,
        public readonly int $adjustment,
        public readonly int $discount,
        public readonly int $tax,
        public readonly int $final,
        public readonly array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'stay_date' => $this->date,
            'base_amount' => $this->base,
            'adjustment_amount' => $this->adjustment,
            'discount_amount' => $this->discount,
            'tax_amount' => $this->tax,
            'final_amount' => $this->final,
            'pricing_metadata' => $this->metadata,
        ];
    }
}
