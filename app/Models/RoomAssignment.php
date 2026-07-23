<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAssignment extends Model
{
    protected $fillable = [
        'booking_item_id', 'room_id', 'stay_from', 'stay_to',
        'assigned_at', 'assigned_by', 'check_in_at', 'check_out_at',
    ];

    protected function casts(): array
    {
        return [
            'stay_from' => 'date',
            'stay_to' => 'date',
            'assigned_at' => 'datetime',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Assignments for a room that overlap [$from, $to) — checkout day excluded,
     * so a guest leaving on the 12th doesn't clash with one arriving on the 12th.
     */
    public function scopeOverlapping(Builder $query, int $roomId, string $from, string $to): Builder
    {
        return $query->where('room_id', $roomId)
            ->whereNull('check_out_at')
            ->where('stay_from', '<', $to)
            ->where('stay_to', '>', $from);
    }
}
