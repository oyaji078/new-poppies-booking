<?php

namespace App\Services\Booking;

use App\Enums\AuditAction;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Services\Audit\AuditLogger;
use App\Support\StayPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Admin-facing inventory management (the inventory calendar). Enforces the §30
 * guard rules and audits every override. Booking-time inventory mutation lives in
 * BookingInventoryService (Phase 4) which locks rows; this service is for manual
 * admin adjustments, not concurrent booking flow.
 */
class InventoryService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Ensure inventory rows exist for each given date, defaulting total to the
     * room type's configured inventory. Returns the rows keyed by Y-m-d.
     *
     * @param  array<int, string>  $dates
     * @return array<string, RoomTypeInventory>
     */
    public function ensureRows(RoomType $roomType, array $dates): array
    {
        $existing = RoomTypeInventory::query()
            ->where('room_type_id', $roomType->id)
            ->whereIn('inventory_date', $dates)
            ->get()
            ->keyBy(fn (RoomTypeInventory $r) => $r->inventory_date->toDateString());

        foreach ($dates as $date) {
            if (! $existing->has($date)) {
                $existing->put($date, RoomTypeInventory::create([
                    'room_type_id' => $roomType->id,
                    'inventory_date' => $date,
                    'total_inventory' => $roomType->default_inventory,
                    'blocked_inventory' => 0,
                    'held_inventory' => 0,
                    'confirmed_inventory' => 0,
                ]));
            }
        }

        return $existing->all();
    }

    public function setTotal(RoomTypeInventory $row, int $total): void
    {
        if ($total < $row->confirmed_inventory) {
            throw new RuntimeException('Total inventaris tidak boleh lebih kecil dari kamar yang sudah terkonfirmasi.');
        }
        if ($total < $row->blocked_inventory) {
            throw new RuntimeException('Total inventaris tidak boleh lebih kecil dari inventaris yang diblokir.');
        }

        $old = $row->total_inventory;
        $row->update(['total_inventory' => $total]);
        $this->audit->log(AuditAction::INVENTORY_CHANGE->value, $row, ['total_inventory' => $old], ['total_inventory' => $total]);
    }

    public function setBlocked(RoomTypeInventory $row, int $blocked): void
    {
        $blocked = max(0, $blocked);
        if ($blocked > $row->total_inventory - $row->confirmed_inventory - $row->held_inventory) {
            throw new RuntimeException('Inventaris yang diblokir melebihi kamar yang tersedia untuk diblokir.');
        }

        $old = $row->blocked_inventory;
        $row->update(['blocked_inventory' => $blocked]);
        $this->audit->log(AuditAction::INVENTORY_CHANGE->value, $row, ['blocked_inventory' => $old], ['blocked_inventory' => $blocked]);
    }

    public function setPrice(RoomType $roomType, string $date, int $price): void
    {
        RatePlan::updateOrCreate(
            ['room_type_id' => $roomType->id, 'rate_date' => $date],
            ['price' => max(0, $price)],
        );

        $this->audit->log(AuditAction::PRICE_CHANGE->value, $roomType, null, ['date' => $date, 'price' => $price]);
    }

    /**
     * Bulk apply over a date range (inclusive of start, exclusive of end night).
     *
     * @param  array{total?: int, blocked?: int, price?: int}  $changes
     */
    public function bulkUpdate(RoomType $roomType, string $start, string $end, array $changes): int
    {
        $stay = new StayPeriod($start, $end);
        $dates = $stay->stayDateStrings();

        return DB::transaction(function () use ($roomType, $dates, $changes) {
            $rows = $this->ensureRows($roomType, $dates);
            $count = 0;

            foreach ($rows as $date => $row) {
                if (array_key_exists('total', $changes)) {
                    $this->setTotal($row, (int) $changes['total']);
                }
                if (array_key_exists('blocked', $changes)) {
                    $this->setBlocked($row->fresh(), (int) $changes['blocked']);
                }
                if (array_key_exists('price', $changes)) {
                    $this->setPrice($roomType, $date, (int) $changes['price']);
                }
                $count++;
            }

            return $count;
        });
    }

    /**
     * Calendar rows for a room type across a month, with rate overrides merged in.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calendar(RoomType $roomType, CarbonImmutable $monthStart): array
    {
        $monthEnd = $monthStart->endOfMonth();
        $dates = [];
        for ($d = $monthStart; $d <= $monthEnd; $d = $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        $inventories = RoomTypeInventory::query()
            ->where('room_type_id', $roomType->id)
            ->whereIn('inventory_date', $dates)
            ->get()
            ->keyBy(fn ($r) => $r->inventory_date->toDateString());

        $rates = RatePlan::query()
            ->where('room_type_id', $roomType->id)
            ->whereIn('rate_date', $dates)
            ->get()
            ->keyBy(fn ($r) => $r->rate_date->toDateString());

        $out = [];
        foreach ($dates as $date) {
            $row = $inventories->get($date);
            $out[] = [
                'date' => $date,
                'total' => $row?->total_inventory ?? $roomType->default_inventory,
                'held' => $row?->held_inventory ?? 0,
                'confirmed' => $row?->confirmed_inventory ?? 0,
                'blocked' => $row?->blocked_inventory ?? 0,
                'available' => $row ? $row->available() : $roomType->default_inventory,
                'price' => $rates->get($date)?->price ?? $roomType->base_price,
                'has_override' => $rates->has($date),
            ];
        }

        return $out;
    }
}
