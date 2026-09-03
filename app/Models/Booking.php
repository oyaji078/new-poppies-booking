<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Support\StayPeriod;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'user_id',
        'customer_name', 'customer_email', 'customer_phone', 'customer_country',
        'check_in_date', 'check_out_date', 'nights', 'rooms', 'adults', 'children',
        'status', 'payment_status',
        'subtotal_amount', 'discount_amount', 'tax_amount', 'service_amount', 'total_amount', 'currency',
        'promotion_id', 'promotion_code',
        'special_request', 'arrival_time', 'cancellation_policy',
        'held_until', 'confirmed_at', 'cancelled_at', 'checked_in_at', 'checked_out_at',
        'cancellation_reason', 'late_payment_recovery', 'review_inventory_held',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'nights' => 'integer',
            'rooms' => 'integer',
            'adults' => 'integer',
            'children' => 'integer',
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'service_amount' => 'integer',
            'total_amount' => 'integer',
            'cancellation_policy' => 'array',
            'held_until' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'late_payment_recovery' => 'boolean',
            'review_inventory_held' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(BookingGuest::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function stayPeriod(): StayPeriod
    {
        return new StayPeriod($this->check_in_date->toDateString(), $this->check_out_date->toDateString());
    }

    /**
     * Move the booking to a new status, rejecting transitions the state machine
     * does not allow. Guards that depend on inventory/payment live in services.
     */
    public function transitionTo(BookingStatus $target): void
    {
        if ($this->status === $target) {
            return;
        }

        if (! $this->status->canTransitionTo($target)) {
            throw new DomainException(
                "Transisi status tidak valid: {$this->status->value} -> {$target->value}."
            );
        }

        $this->status = $target;
    }

    public function isHoldExpired(): bool
    {
        return $this->held_until !== null && $this->held_until->isPast();
    }

    public function isPayable(): bool
    {
        return in_array($this->status, [BookingStatus::HELD, BookingStatus::PENDING_PAYMENT], true)
            && ! $this->isHoldExpired();
    }

    public function activePaymentAttempt(): ?PaymentAttempt
    {
        return $this->paymentAttempts()
            ->where('status', PaymentAttemptStatus::PENDING->value)
            ->latest('id')
            ->first();
    }

    /**
     * Total successfully refunded so far (integer rupiah).
     */
    public function refundedAmount(): int
    {
        return (int) $this->refunds()
            ->where('status', RefundStatus::SUCCEEDED->value)
            ->sum('amount');
    }

    public function scopeExpiredHolds(Builder $query): Builder
    {
        return $query->whereIn('status', [BookingStatus::HELD->value, BookingStatus::PENDING_PAYMENT->value])
            ->whereNotNull('held_until')
            ->where('held_until', '<', now());
    }
}
