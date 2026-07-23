<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingItem extends Model
{
    protected $fillable = [
        'booking_id', 'room_type_id', 'room_type_name', 'rooms', 'subtotal_amount',
    ];

    protected function casts(): array
    {
        return [
            'rooms' => 'integer',
            'subtotal_amount' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function nights(): HasMany
    {
        return $this->hasMany(BookingItemNight::class)->orderBy('stay_date');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class);
    }
}
