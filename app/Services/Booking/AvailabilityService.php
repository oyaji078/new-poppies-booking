<?php

namespace App\Services\Booking;

use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Support\StayPeriod;
use Illuminate\Support\Collection;

/**
 * Computes room-type availability from daily inventory (never from a raw physical
 * room count). A multi-night stay is available only when EVERY stay night has
 * enough inventory. Current maintenance/inactive rooms further cap availability.
 */
class AvailabilityService
{
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
     * Search published room types that can host the requested party for the whole stay.
     *
     * @return Collection<int, array{room_type: RoomType, available: int}>
     */
    public function search(StayPeriod $stay, int $adults, int $children, int $rooms): Collection
    {
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
