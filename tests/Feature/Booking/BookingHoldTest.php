<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingException;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Services\Booking\BookingExpirationService;
use App\Services\Booking\BookingService;
use App\Services\Settings\SettingService;
use App\Support\StayPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingHoldTest extends TestCase
{
    use RefreshDatabase;

    private string $checkIn;

    private string $checkOut;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(SettingService::class);
        $settings->set('tax_percent', 10, 'integer');
        $settings->set('service_percent', 5, 'integer');
        $settings->set('weekend_surcharge_percent', 0, 'integer');
        $settings->set('extra_guest_fee', 0, 'integer');
        $settings->set('booking_hold_minutes', 30, 'integer');
        $settings->set('booking_max_nights', 30, 'integer');

        $this->checkIn = now()->addDays(10)->toDateString();
        $this->checkOut = now()->addDays(12)->toDateString(); // 2 nights
    }

    private function roomType(int $units = 2, int $price = 1_000_000): RoomType
    {
        $roomType = RoomType::factory()->create([
            'default_inventory' => $units,
            'base_price' => $price,
            'adult_capacity' => 2,
            'max_guests' => 4,
        ]);
        Room::factory()->count($units)->create(['room_type_id' => $roomType->id]);

        foreach ((new StayPeriod($this->checkIn, $this->checkOut))->stayDateStrings() as $date) {
            RoomTypeInventory::create([
                'room_type_id' => $roomType->id, 'inventory_date' => $date,
                'total_inventory' => $units, 'held_inventory' => 0, 'confirmed_inventory' => 0, 'blocked_inventory' => 0,
            ]);
        }

        return $roomType;
    }

    private function details(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Budi Santoso',
            'customer_email' => 'budi@example.com',
            'customer_phone' => '081234567890',
            'customer_country' => 'Indonesia',
        ], $overrides);
    }

    public function test_it_creates_a_hold_with_snapshots_and_reserves_inventory(): void
    {
        $roomType = $this->roomType(2);
        $stay = new StayPeriod($this->checkIn, $this->checkOut);

        $booking = app(BookingService::class)->createHold($roomType, $stay, rooms: 1, adults: 2, children: 0, details: $this->details());

        $this->assertSame(BookingStatus::HELD, $booking->status);
        $this->assertSame(PaymentStatus::UNPAID, $booking->payment_status);
        $this->assertMatchesRegularExpression('/^NPS-\d{8}-[A-Z0-9]{6}$/', $booking->code);
        $this->assertNotNull($booking->held_until);

        // Server-computed totals: 2 nights x 1,000,000 = 2,000,000 (+10% tax, +5% service)
        $this->assertSame(2_000_000, $booking->subtotal_amount);
        $this->assertSame(200_000, $booking->tax_amount);
        $this->assertSame(100_000, $booking->service_amount);
        $this->assertSame(2_300_000, $booking->total_amount);

        // One nightly snapshot per stay night, summing to the grand total.
        $nights = $booking->items->first()->nights;
        $this->assertCount(2, $nights);
        $this->assertSame($booking->total_amount, $nights->sum('final_amount'));

        // Inventory held on every stay night.
        foreach ($stay->stayDateStrings() as $date) {
            $this->assertDatabaseHas('room_type_inventories', [
                'room_type_id' => $roomType->id, 'inventory_date' => $date, 'held_inventory' => 1,
            ]);
        }
    }

    public function test_it_rejects_when_no_inventory_remains(): void
    {
        $roomType = $this->roomType(1);
        $stay = new StayPeriod($this->checkIn, $this->checkOut);
        $service = app(BookingService::class);

        $service->createHold($roomType, $stay, 1, 2, 0, $this->details());

        $this->expectException(BookingException::class);
        $service->createHold($roomType, $stay, 1, 2, 0, $this->details(['customer_email' => 'two@example.com']));
    }

    public function test_it_rejects_when_one_night_of_the_range_is_full(): void
    {
        $roomType = $this->roomType(2);
        $stay = new StayPeriod($this->checkIn, $this->checkOut);

        // Fill only the second night.
        RoomTypeInventory::where('room_type_id', $roomType->id)
            ->whereDate('inventory_date', now()->addDays(11)->toDateString())
            ->update(['confirmed_inventory' => 2]);

        $this->expectException(BookingException::class);
        app(BookingService::class)->createHold($roomType, $stay, 1, 2, 0, $this->details());
    }

    public function test_it_rejects_check_in_in_the_past(): void
    {
        $roomType = $this->roomType(2);

        $this->expectException(BookingException::class);
        app(BookingService::class)->createHold(
            $roomType,
            new StayPeriod(now()->subDays(2)->toDateString(), now()->addDay()->toDateString()),
            1, 2, 0, $this->details()
        );
    }

    public function test_it_rejects_when_guests_exceed_room_capacity(): void
    {
        $roomType = $this->roomType(2);          // max_guests = 4
        $stay = new StayPeriod($this->checkIn, $this->checkOut);

        $this->expectException(BookingException::class);
        app(BookingService::class)->createHold($roomType, $stay, rooms: 1, adults: 6, children: 0, details: $this->details());
    }

    public function test_expired_hold_releases_inventory_and_marks_booking_expired(): void
    {
        $roomType = $this->roomType(1);
        $stay = new StayPeriod($this->checkIn, $this->checkOut);

        $booking = app(BookingService::class)->createHold($roomType, $stay, 1, 2, 0, $this->details());
        $booking->forceFill(['held_until' => now()->subMinute()])->save();

        $expired = app(BookingExpirationService::class)->expireDueHolds();

        $this->assertSame(1, $expired);
        $booking->refresh();
        $this->assertSame(BookingStatus::EXPIRED, $booking->status);
        $this->assertSame(PaymentStatus::EXPIRED, $booking->payment_status);

        foreach ($stay->stayDateStrings() as $date) {
            $this->assertDatabaseHas('room_type_inventories', [
                'room_type_id' => $roomType->id, 'inventory_date' => $date, 'held_inventory' => 0,
            ]);
        }
    }

    public function test_expiring_twice_does_not_double_release_inventory(): void
    {
        $roomType = $this->roomType(1);
        $stay = new StayPeriod($this->checkIn, $this->checkOut);

        $booking = app(BookingService::class)->createHold($roomType, $stay, 1, 2, 0, $this->details());
        $booking->forceFill(['held_until' => now()->subMinute()])->save();

        $service = app(BookingExpirationService::class);
        $this->assertSame(1, $service->expireDueHolds());
        $this->assertSame(0, $service->expireDueHolds()); // already handled

        foreach ($stay->stayDateStrings() as $date) {
            $this->assertDatabaseHas('room_type_inventories', [
                'room_type_id' => $roomType->id, 'inventory_date' => $date, 'held_inventory' => 0,
            ]);
        }
    }

    public function test_inventory_is_freed_for_a_new_booking_after_expiry(): void
    {
        $roomType = $this->roomType(1);
        $stay = new StayPeriod($this->checkIn, $this->checkOut);
        $service = app(BookingService::class);

        $first = $service->createHold($roomType, $stay, 1, 2, 0, $this->details());
        $first->forceFill(['held_until' => now()->subMinute()])->save();
        app(BookingExpirationService::class)->expireDueHolds();

        // The last room is bookable again.
        $second = $service->createHold($roomType, $stay, 1, 2, 0, $this->details(['customer_email' => 'second@example.com']));
        $this->assertSame(BookingStatus::HELD, $second->status);
    }
}
