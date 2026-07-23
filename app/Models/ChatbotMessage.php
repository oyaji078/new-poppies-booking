<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotMessage extends Model
{
    protected $fillable = ['chatbot_session_id', 'role', 'content', 'faq_id', 'was_answered'];

    protected function casts(): array
    {
        return ['was_answered' => 'boolean'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatbotSession::class, 'chatbot_session_id');
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class);
    }
}
