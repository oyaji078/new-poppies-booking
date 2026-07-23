<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatbotSession extends Model
{
    protected $fillable = ['session_key', 'user_id', 'last_activity_at'];

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class)->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
