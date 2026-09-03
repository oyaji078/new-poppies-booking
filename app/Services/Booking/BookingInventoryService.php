<?php

namespace App\Services\Booking;

use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Support\StayPeriod;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * The double-booking guard (§9).
 *
 * Every mutation of held/confirmed inventory goes through here, always:
 *   1. inside a transaction (the CALLER opens it),
 *   2. after SELECT ... FOR UPDATE on every affected inventory row,
 *   3. locking rows in a deterministic order (inventory_date ASC) so two
 *      concurrent bookings can never deadlock by grabbing rows in opposite order.
 *
 * Inventory values are guarded so they can never go negative.
 */
class BookingInventoryService
{
    /**
     * Lock the inventory rows for the stay, creating any that are missing.
     * MUST be called inside a transaction.
     *
     * @return array<string, RoomTypeInventory> keyed by Y-m-d, ordered by date
     */
    public function lockRows(RoomType $roomType, StayPeriod $stay): array
    {
        $dates = $stay->stayDateStrings();

        // Create missing rows first so the lock below covers every stay night.
        // Uses insertOrIgnore to stay safe when two requests race here; the unique
        // (room_type_id, inventory_date) index makes the loser a no-op.
        $now = now();
        $missing = [];
        $existingDates = RoomTypeInventory::query()
            ->where('room_type_id', $roomType->id)
            ->whereIn('inventory_date', $dates)
            ->pluck('inventory_date')
            ->map(fn ($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : (string) $d)
            ->all();

        foreach (array_diff($dates, $existingDates) as $date) {
            $missing[] = [
                'room_type_id' => $roomType->id,
                'inventory_date' => $date,
                'total_inventory' => $roomType->default_inventory,
                'blocked_inventory' => 0,
                'held_inventory' => 0,
                'confirmed_inventory' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($missing !== []) {
            RoomTypeInventory::query()->insertOrIgnore($missing);
        }

        // Deterministic lock order — always ascending by date.
        $rows = RoomTypeInventory::query()
            ->where('room_type_id', $roomType->id)
            ->whereIn('inventory_date', $dates)
            ->orderBy('inventory_date')
            ->lockForUpdate()
            ->get();

        return $rows->keyBy(fn (RoomTypeInventory $r) => $r->inventory_date->toDateString())->all();
    }

    /**
     * True when every stay night has at least $rooms units free.
     * Expects rows already locked by {@see lockRows()}.
     *
     * @param  array<string, RoomTypeInventory>  $lockedRows
     */
    public function hasCapacity(array $lockedRows, StayPeriod $stay, int $rooms, int $sellableCap): bool
    {
        foreach ($stay->stayDateStrings() as $date) {
            $row = $lockedRows[$date] ?? null;
            if (! $row) {
                return false;
            }

            if (min($row->available(), $sellableCap) < $rooms) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, RoomTypeInventory>  $lockedRows
     */
    public function increaseHeld(array $lockedRows, int $rooms): void
    {
        $this->bump($lockedRows, 'held_inventory', $rooms);
    }

    /**
     * @param  array<string, RoomTypeInventory>  $lockedRows
     */
    public function releaseHeld(array $lockedRows, int $rooms): void
    {
        $this->bump($lockedRows, 'held_inventory', -$rooms);
    }

    /**
     * Held -> confirmed, atomically per row.
     *
     * @param  array<string, RoomTypeInventory>  $lockedRows
     */
    public function convertHeldToConfirmed(array $lockedRows, int $rooms): void
    {
        foreach ($lockedRows as $row) {
            RoomTypeInventory::query()->whereKey($row->getKey())->update([
                'held_inventory' => $this->clampedSubtraction('held_inventory', $rooms),
                'confirmed_inventory' => new Expression('confirmed_inventory + '.(int) $rooms),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reserve directly as confirmed (late-payment recovery after a hold expired).
     *
     * @param  array<string, RoomTypeInventory>  $lockedRows
     */
    public function increaseConfirmed(array $lockedRows, int $rooms): void
    {
        $this->bump($lockedRows, 'confirmed_inventory', $rooms);
    }

    /**
     * @param  array<string, RoomTypeInventory>  $lockedRows
     */
    public function releaseConfirmed(array $lockedRows, int $rooms): void
    {
        $this->bump($lockedRows, 'confirmed_inventory', -$rooms);
    }

    /**
     * @param  array<string, RoomTypeInventory>  $lockedRows
     */
    private function bump(array $lockedRows, string $column, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        foreach ($lockedRows as $row) {
            $expression = $delta > 0
                ? new Expression("{$column} + ".$delta)
                : $this->clampedSubtraction($column, abs($delta));

            RoomTypeInventory::query()->whereKey($row->getKey())->update([
                $column => $expression,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Subtract without ever going below zero.
     *
     * NOT GREATEST(col - n, 0): these columns are UNSIGNED, so MySQL evaluates
     * the subtraction first and aborts with "BIGINT UNSIGNED value is out of
     * range" before GREATEST can clamp anything — the drift-safety net would
     * fail exactly when drift occurs. CASE never performs the underflowing
     * subtraction at all, and is portable to PostgreSQL.
     */
    private function clampedSubtraction(string $column, int $amount): Expression
    {
        $amount = (int) $amount;

        return new Expression(
            "CASE WHEN {$column} >= {$amount} THEN {$column} - {$amount} ELSE 0 END"
        );
    }

    /**
     * Run a closure inside a transaction, retrying a limited number of times on
     * deadlock / lock-wait timeout (MySQL 1213 / 1205).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function transactionWithRetry(callable $callback, int $attempts = 3): mixed
    {
        // Laravel's own transaction() already retries on deadlock when given
        // an attempts count, and re-throws once exhausted.
        return DB::transaction($callback, $attempts);
    }
}
