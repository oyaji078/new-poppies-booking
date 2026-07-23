<?php

namespace App\Services\Operations;

use App\Enums\AuditAction;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Check-in and physical room assignment (§23).
 *
 * A physical room can never be assigned to two overlapping stays. The overlap
 * test treats the checkout date as exclusive, so back-to-back stays are fine.
 */
class CheckInService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Rooms that can legitimately host this booking item right now.
     *
     * @return Collection<int, Room>
     */
    public function availableRoomsFor(Booking $booking, int $roomTypeId): Collection
    {
        $from = $booking->check_in_date->toDateString();
        $to = $booking->check_out_date->toDateString();

        return Room::query()
            ->where('room_type_id', $roomTypeId)
            ->sellable()
            ->whereNotExists(function ($q) use ($from, $to) {
                $q->select(DB::raw(1))
                    ->from('room_assignments')
                    ->whereColumn('room_assignments.room_id', 'rooms.id')
                    ->whereNull('room_assignments.check_out_at')
                    ->where('room_assignments.stay_from', '<', $to)
                    ->where('room_assignments.stay_to', '>', $from);
            })
            ->orderBy('room_number')
            ->get();
    }

    /**
     * @param  array<int, array<int, int>>  $roomIdsByItem  booking_item_id => [room_id, ...]
     * @param  array<int, array{full_name: string, id_card_type?: ?string, id_card_number?: ?string}>  $guests
     */
    public function checkIn(Booking $booking, array $roomIdsByItem, User $admin, array $guests = [], ?string $earlyReason = null): Booking
    {
        return DB::transaction(function () use ($booking, $roomIdsByItem, $admin, $guests, $earlyReason) {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if ($locked->status !== BookingStatus::CONFIRMED) {
                throw new RuntimeException('Hanya pemesanan berstatus terkonfirmasi yang dapat check-in.');
            }

            // Early check-in is allowed but must be justified and audited.
            $isEarly = today()->lt($locked->check_in_date);
            if ($isEarly && trim((string) $earlyReason) === '') {
                throw new RuntimeException('Check-in lebih awal memerlukan alasan.');
            }

            if (today()->gt($locked->check_out_date)) {
                throw new RuntimeException('Tanggal menginap sudah lewat.');
            }

            $from = $locked->check_in_date->toDateString();
            $to = $locked->check_out_date->toDateString();

            foreach ($locked->items as $item) {
                $roomIds = array_values(array_unique($roomIdsByItem[$item->id] ?? []));

                if (count($roomIds) !== (int) $item->rooms) {
                    throw new RuntimeException("Pilih tepat {$item->rooms} kamar untuk {$item->room_type_name}.");
                }

                foreach ($roomIds as $roomId) {
                    /** @var Room|null $room */
                    $room = Room::query()->whereKey($roomId)->lockForUpdate()->first();

                    if (! $room) {
                        throw new RuntimeException('Kamar tidak ditemukan.');
                    }
                    if ($room->room_type_id !== $item->room_type_id) {
                        throw new RuntimeException("Kamar {$room->room_number} bukan bagian dari tipe {$item->room_type_name}.");
                    }
                    if (! $room->isSellable()) {
                        throw new RuntimeException("Kamar {$room->room_number} tidak aktif atau sedang dalam pemeliharaan.");
                    }

                    // Overlap guard, re-checked under the row lock.
                    $conflict = RoomAssignment::query()
                        ->overlapping($room->id, $from, $to)
                        ->exists();

                    if ($conflict) {
                        throw new RuntimeException("Kamar {$room->room_number} sudah ditempati pada rentang tanggal ini.");
                    }

                    RoomAssignment::create([
                        'booking_item_id' => $item->id,
                        'room_id' => $room->id,
                        'stay_from' => $from,
                        'stay_to' => $to,
                        'assigned_at' => now(),
                        'assigned_by' => $admin->id,
                        'check_in_at' => now(),
                    ]);
                }
            }

            // Guest identity records.
            foreach ($guests as $guest) {
                if (($guest['full_name'] ?? '') === '') {
                    continue;
                }
                $locked->guests()->create([
                    'full_name' => $guest['full_name'],
                    'id_card_type' => $guest['id_card_type'] ?? null,
                    'id_card_number' => $guest['id_card_number'] ?? null,
                    'is_primary' => false,
                ]);
            }

            $locked->transitionTo(BookingStatus::CHECKED_IN);
            $locked->checked_in_at = now();
            $locked->save();

            $this->audit->log(AuditAction::CHECK_IN->value, $locked, null, array_filter([
                'code' => $locked->code,
                'rooms' => $roomIdsByItem,
                'early_check_in_reason' => $isEarly ? $earlyReason : null,
            ]), $admin);

            return $locked;
        });
    }
}
