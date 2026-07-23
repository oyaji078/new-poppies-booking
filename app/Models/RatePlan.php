<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatePlan extends Model
{
    protected $fillable = ['room_type_id', 'rate_date', 'price'];

    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'price' => 'integer',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
