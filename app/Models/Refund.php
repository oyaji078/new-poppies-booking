<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'booking_id', 'payment_attempt_id', 'amount', 'status', 'reason',
        'provider_reference', 'is_manual', 'requested_at', 'processed_at', 'processed_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => RefundStatus::class,
            'is_manual' => 'boolean',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
