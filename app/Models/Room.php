<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_type_id', 'room_number', 'floor',
        'is_active', 'under_maintenance', 'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'under_maintenance' => 'boolean',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('under_maintenance', false);
    }

    public function isSellable(): bool
    {
        return $this->is_active && ! $this->under_maintenance;
    }
}
