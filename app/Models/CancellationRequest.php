<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationRequest extends Model
{
    protected $fillable = [
        'booking_id', 'requested_by', 'requested_by_type', 'reason',
        'eligible_for_refund', 'refund_estimate', 'policy_snapshot', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'eligible_for_refund' => 'boolean',
            'refund_estimate' => 'integer',
            'policy_snapshot' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
