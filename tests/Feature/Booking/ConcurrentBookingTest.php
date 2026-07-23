<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Support\StayPeriod;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TRUE concurrency test (§36).
 *
 * This test deliberately does NOT use RefreshDatabase: that trait wraps each test
 * in a transaction, which would hide the very race we are trying to prove. Instead
 * it commits real rows and launches several SEPARATE OS processes that all try to
 * grab the last room at the same instant.
 *
 * Expected: exactly one succeeds, the rest are cleanly rejected, and inventory
 * never exceeds capacity or goes negative.
 */
class ConcurrentBookingTest extends TestCase
{
    private ?RoomType $roomType = null;

    private string $checkIn;

    private string $checkOut;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tag = 'conc-'.Str::random(6);
        $this->checkIn = now()->addDays(60)->toDateString();
        $this->checkOut = now()->addDays(62)->toDateString();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        if (! $this->roomType) {
            return;
        }

        $bookingIds = Booking::where('customer_email', 'like', $this->tag.'%')->pluck('id');
        if ($bookingIds->isNotEmpty()) {
            DB::table('booking_item_nights')
                ->whereIn('booking_item_id', DB::table('booking_items')->whereIn('booking_id', $bookingIds)->pluck('id'))
                ->delete();
            DB::table('booking_items')->whereIn('booking_id', $bookingIds)->delete();
            DB::table('booking_guests')->whereIn('booking_id', $bookingIds)->delete();
            DB::table('bookings')->whereIn('id', $bookingIds)->delete();
        }

        RoomTypeInventory::where('room_type_id', $this->roomType->id)->delete();
        Room::where('room_type_id', $this->roomType->id)->delete();
        RoomType::whereKey($this->roomType->id)->delete();
        $this->roomType = null;
    }

    /**
     * Seed a room type with exactly ONE sellable room, committed to the database.
     */
    private function seedLastRoom(int $units = 1): void
    {
        $this->roomType = RoomType::create([
            'name' => 'Concurrency '.$this->tag,
            'slug' => 'concurrency-'.$this->tag,
            'short_description' => 'Concurrency fixture',
            'adult_capacity' => 2,
            'child_capacity' => 0,
            'max_guests' => 4,
            'base_price' => 1_000_000,
            'default_inventory' => $units,
            'is_published' => true,
        ]);

        for ($i = 0; $i < $units; $i++) {
            Room::create([
                'room_type_id' => $this->roomType->id,
                'room_number' => strtoupper($this->tag).'-'.$i,
                'is_active' => true,
                'under_maintenance' => false,
            ]);
        }

        foreach ((new StayPeriod($this->checkIn, $this->checkOut))->stayDateStrings() as $date) {
            RoomTypeInventory::create([
                'room_type_id' => $this->roomType->id,
                'inventory_date' => $date,
                'total_inventory' => $units,
                'blocked_inventory' => 0,
                'held_inventory' => 0,
                'confirmed_inventory' => 0,
            ]);
        }
    }

    /**
     * Fire N booking attempts in parallel, synchronised to the same start instant.
     *
     * @return array<int, string> raw output lines
     */
    private function raceFor(int $attempts, int $roomsEach = 1): array
    {
        // Give every process time to boot, then release them together.
        $startAt = microtime(true) + 3.0;

        // Children must talk to the TEST database, not the app one. Process env
        // wins over .env because Laravel loads .env immutably.
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
        ];

        $results = Process::pool(function (Pool $pool) use ($attempts, $roomsEach, $startAt, $env) {
            for ($i = 0; $i < $attempts; $i++) {
                $pool->path(base_path())
                    ->env($env)
                    ->timeout(60)
                    ->command([
                        PHP_BINARY,
                        'artisan',
                        'booking:attempt-hold',
                        (string) $this->roomType->id,
                        $this->checkIn,
                        $this->checkOut,
                        $this->tag.'-'.$i.'@example.test',
                        '--rooms='.$roomsEach,
                        '--startAt='.$startAt,
                    ]);
            }
        })->start()->wait();

        // ProcessPoolResults only implements ArrayAccess — collect() to iterate.
        return $results->collect()
            ->map(fn ($result) => trim($result->output()."\n".$result->errorOutput()))
            ->all();
    }

    public function test_only_one_of_four_simultaneous_requests_gets_the_last_room(): void
    {
        $this->seedLastRoom(units: 1);

        $outputs = $this->raceFor(attempts: 4);

        $successes = array_filter($outputs, fn ($o) => str_contains($o, 'SUCCESS:'));
        $rejections = array_filter($outputs, fn ($o) => str_contains($o, 'REJECTED:'));
        $errors = array_filter($outputs, fn ($o) => str_contains($o, 'ERROR:'));

        $this->assertCount(0, $errors, "Unexpected errors:\n".implode("\n", $outputs));
        $this->assertCount(1, $successes, "Exactly one booking must win the last room. Got:\n".implode("\n", $outputs));
        $this->assertCount(3, $rejections, "The other three must be cleanly rejected. Got:\n".implode("\n", $outputs));

        // Exactly one booking row exists for this fixture.
        $this->assertSame(1, Booking::where('customer_email', 'like', $this->tag.'%')->count());

        // Inventory is exactly at capacity on every stay night — never over, never negative.
        $rows = RoomTypeInventory::where('room_type_id', $this->roomType->id)->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame(1, $row->held_inventory, 'Held inventory must be exactly 1.');
            $this->assertGreaterThanOrEqual(0, $row->available(), 'Availability must never go negative.');
            $this->assertLessThanOrEqual(
                $row->total_inventory,
                $row->held_inventory + $row->confirmed_inventory + $row->blocked_inventory,
                'Inventory must never be oversold.'
            );
        }
    }

    public function test_three_rooms_are_not_oversold_by_six_simultaneous_requests(): void
    {
        $this->seedLastRoom(units: 3);

        $outputs = $this->raceFor(attempts: 6);

        $successes = array_filter($outputs, fn ($o) => str_contains($o, 'SUCCESS:'));
        $errors = array_filter($outputs, fn ($o) => str_contains($o, 'ERROR:'));

        $this->assertCount(0, $errors, "Unexpected errors:\n".implode("\n", $outputs));
        $this->assertCount(3, $successes, "Exactly three bookings may succeed. Got:\n".implode("\n", $outputs));

        foreach (RoomTypeInventory::where('room_type_id', $this->roomType->id)->get() as $row) {
            $this->assertSame(3, $row->held_inventory);
            $this->assertSame(0, $row->available());
            $this->assertGreaterThanOrEqual(0, $row->available());
        }
    }
}
