<?php

namespace App\Models;

use App\Enums\PromotionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'type', 'value', 'max_discount', 'is_automatic',
        'min_nights', 'min_transaction', 'usage_limit', 'used_count',
        'booking_start', 'booking_end', 'stay_start', 'stay_end', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'value' => 'integer',
            'max_discount' => 'integer',
            'is_automatic' => 'boolean',
            'min_nights' => 'integer',
            'min_transaction' => 'integer',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'booking_start' => 'date',
            'booking_end' => 'date',
            'stay_start' => 'date',
            'stay_end' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(RoomType::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->where('is_automatic', true);
    }

    public function hasQuotaLeft(): bool
    {
        return $this->usage_limit === null || $this->used_count < $this->usage_limit;
    }

    public function appliesToRoomType(int $roomTypeId): bool
    {
        // No room-type pivot rows means the promo applies to all types.
        if ($this->roomTypes()->doesntExist()) {
            return true;
        }

        return $this->roomTypes()->whereKey($roomTypeId)->exists();
    }
}
