<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable nightly price snapshot. Changing future room prices must never
 * alter an existing booking — these rows are what the guest actually agreed to.
 */
class BookingItemNight extends Model
{
    protected $fillable = [
        'booking_item_id', 'stay_date',
        'base_amount', 'adjustment_amount', 'discount_amount', 'tax_amount', 'final_amount',
        'pricing_metadata',
    ];

    protected function casts(): array
    {
        return [
            'stay_date' => 'date',
            'base_amount' => 'integer',
            'adjustment_amount' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'final_amount' => 'integer',
            'pricing_metadata' => 'array',
        ];
    }

    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class);
    }
}
