<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'booking_id', 'provider', 'invoice_number', 'request_id',
        'amount', 'currency', 'payment_url', 'status',
        'expires_at', 'paid_at', 'failure_code', 'failure_message',
        'request_payload_redacted', 'response_payload_redacted',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentAttemptStatus::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'request_payload_redacted' => 'array',
            'response_payload_redacted' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
