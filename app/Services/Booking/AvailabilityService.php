<?php

namespace App\Services\Booking;

use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Support\StayPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Computes room-type availability from daily inventory (never from a raw physical
 * room count). A multi-night stay is available only when EVERY stay night has
 * enough inventory. Current maintenance/inactive rooms further cap availability.
 */
class AvailabilityService
{
    public function __construct(
        private readonly BookingExpirationService $expiration,
    ) {}

    /**
     * Maximum number of rooms of this type bookable for the entire stay.
     */
    public function availableUnits(RoomType $roomType, StayPeriod $stay): int
    {
        $inventories = $this->inventoryByDate($roomType, $stay);
        $sellableCap = $roomType->sellableRoomCount();

        $min = PHP_INT_MAX;
        foreach ($stay->stayDateStrings() as $date) {
            $row = $inventories->get($date);
            $inventoryAvailable = $row ? $row->available() : $roomType->default_inventory;
            $min = min($min, min($inventoryAvailable, $sellableCap));
        }

        return $min === PHP_INT_MAX ? 0 : max(0, $min);
    }

    public function isAvailable(RoomType $roomType, StayPeriod $stay, int $rooms = 1): bool
    {
        return $rooms > 0 && $this->availableUnits($roomType, $stay) >= $rooms;
    }

    /**
     * How many rooms are free on each individual date in a range — the numbers
     * behind the public availability calendar.
     *
     * Per DATE, not per stay: a date here is a NIGHT. A guest checking out on a
     * fully booked date is fine, because the checkout day is never occupied.
     *
     * @return array<string, int> Y-m-d => rooms still bookable that night
     */
    public function dailyAvailability(RoomType $roomType, string $from, string $through): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($through)->startOfDay();

        if ($end < $start) {
            return [];
        }

        $sellableCap = $roomType->sellableRoomCount();

        // forDateRange treats the end as exclusive (checkout night), so ask for
        // one day past the last night we want to report on.
        $rows = RoomTypeInventory::query()
            ->where('room_type_id', $roomType->id)
            ->forDateRange($start->toDateString(), $end->addDay()->toDateString())
            ->get()
            ->keyBy(fn (RoomTypeInventory $row) => $row->inventory_date->toDateString());

        $result = [];
        for ($date = $start; $date <= $end; $date = $date->addDay()) {
            $key = $date->toDateString();
            $row = $rows->get($key);

            // No row yet simply means nobody has booked this date — the room
            // type's default allotment is still fully available.
            $free = $row ? $row->available() : $roomType->default_inventory;

            $result[$key] = max(0, min($free, $sellableCap));
        }

        return $result;
    }

    /**
     * Search published room types that can host the requested party for the whole stay.
     *
     * @return Collection<int, array{room_type: RoomType, available: int}>
     */
    public function search(StayPeriod $stay, int $adults, int $children, int $rooms): Collection
    {
        // Vercel has no long-running scheduler. Sweep stale holds on real user
        // traffic so expired reservations cannot keep rooms locked forever.
        $this->expiration->expireDueHolds();

        $guestsPerRoom = (int) ceil(($adults + $children) / max(1, $rooms));

        return RoomType::query()
            ->published()
            ->with(['primaryImage', 'amenities'])
            ->withCount(['rooms as sellable_rooms_count' => fn ($q) => $q->where('is_active', true)->where('under_maintenance', false)])
            ->ordered()
            ->get()
            ->map(function (RoomType $roomType) use ($stay) {
                return ['room_type' => $roomType, 'available' => $this->availableUnits($roomType, $stay)];
            })
            ->filter(function (array $row) use ($rooms, $guestsPerRoom) {
                return $row['available'] >= $rooms
                    && $row['room_type']->max_guests >= $guestsPerRoom;
            })
            ->values();
    }

    /**
     * @return Collection<string, RoomTypeInventory> keyed by Y-m-d
     */
    private function inventoryByDate(RoomType $roomType, StayPeriod $stay): Collection
    {
        return RoomTypeInventory::query()
            ->where('room_type_id', $roomType->id)
            ->forDateRange($stay->checkIn->toDateString(), $stay->checkOut->toDateString())
            ->get()
            ->keyBy(fn (RoomTypeInventory $row) => $row->inventory_date->toDateString());
    }
}
