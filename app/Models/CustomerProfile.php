<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'country',
        'id_card_type',
        'id_card_number',
        'address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
