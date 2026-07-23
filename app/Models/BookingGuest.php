<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingGuest extends Model
{
    protected $fillable = [
        'booking_id', 'full_name', 'id_card_type', 'id_card_number', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
