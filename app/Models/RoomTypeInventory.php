<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomTypeInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_type_id', 'inventory_date',
        'total_inventory', 'blocked_inventory', 'held_inventory', 'confirmed_inventory',
    ];

    protected function casts(): array
    {
        return [
            'inventory_date' => 'date',
            'total_inventory' => 'integer',
            'blocked_inventory' => 'integer',
            'held_inventory' => 'integer',
            'confirmed_inventory' => 'integer',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * available = total - blocked - held - confirmed. Never negative in display.
     */
    public function available(): int
    {
        return max(0, $this->total_inventory - $this->blocked_inventory - $this->held_inventory - $this->confirmed_inventory);
    }

    public function scopeForDateRange(Builder $query, string $start, string $end): Builder
    {
        // $end is exclusive — checkout night is not a stay night.
        return $query->where('inventory_date', '>=', $start)->where('inventory_date', '<', $end);
    }
}
