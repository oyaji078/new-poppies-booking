<?php

namespace App\Services\Operations;

use App\Enums\AuditAction;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\RoomAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Check-out (§24). Closes the room assignments so the physical rooms become
 * assignable again. Extra charges are recorded only when explicitly entered —
 * late checkout is never auto-charged.
 */
class CheckOutService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function checkOut(Booking $booking, User $admin, int $extraCharges = 0, ?string $notes = null): Booking
    {
        return DB::transaction(function () use ($booking, $admin, $extraCharges, $notes) {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if ($locked->status !== BookingStatus::CHECKED_IN) {
                throw new RuntimeException('Hanya tamu yang sudah check-in yang dapat di-check-out.');
            }

            if ($extraCharges < 0) {
                throw new RuntimeException('Biaya tambahan tidak boleh negatif.');
            }

            // Close every open assignment for this booking.
            $itemIds = $locked->items->pluck('id');
            RoomAssignment::query()
                ->whereIn('booking_item_id', $itemIds)
                ->whereNull('check_out_at')
                ->update(['check_out_at' => now(), 'updated_at' => now()]);

            $locked->transitionTo(BookingStatus::CHECKED_OUT);
            $locked->checked_out_at = now();
            $locked->save();

            $this->audit->log(AuditAction::CHECK_OUT->value, $locked, null, array_filter([
                'code' => $locked->code,
                'extra_charges' => $extraCharges ?: null,
                'notes' => $notes,
            ]), $admin);

            return $locked;
        });
    }

    /**
     * Mark a confirmed booking whose check-in date has passed as a no-show.
     */
    public function markNoShow(Booking $booking, User $admin, string $reason): Booking
    {
        if (trim($reason) === '') {
            throw new RuntimeException('Alasan wajib diisi.');
        }

        return DB::transaction(function () use ($booking, $admin, $reason) {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if ($locked->status !== BookingStatus::CONFIRMED) {
                throw new RuntimeException('Hanya pemesanan terkonfirmasi yang dapat ditandai tidak hadir.');
            }

            $locked->transitionTo(BookingStatus::NO_SHOW);
            $locked->save();

            $this->audit->log(AuditAction::NO_SHOW->value, $locked, null, [
                'code' => $locked->code,
                'reason' => $reason,
            ], $admin);

            return $locked;
        });
    }
}
