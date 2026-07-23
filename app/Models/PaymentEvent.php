<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    protected $fillable = [
        'provider', 'payment_attempt_id', 'provider_request_id', 'event_type',
        'payload_hash', 'signature_valid', 'processing_status', 'payload_redacted',
        'received_at', 'processed_at', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload_redacted' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }
}
