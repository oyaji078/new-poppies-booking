<?php

namespace Tests\Feature\Operations;

use App\Enums\BookingStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Services\Operations\CancellationService;
use App\Services\Settings\SettingService;
use App\Support\StayPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Automatic refund on cancellation: the amount is computed with a tiered fee and
 * a refund record is raised for the admin to pay out — no manual arithmetic.
 */
class CancellationFeeTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();

        // The fee tiers are decided by the clock relative to check-in time, so
        // the clock has to be fixed. Left free, "check-in is N hours away" flips
        // tier depending on the hour the suite happens to run at.
        Carbon::setTestNow(Carbon::parse('2026-08-16 20:00:00', 'Asia/Makassar'));

        app(SettingService::class)->set('free_cancellation_hours', 24, 'integer');
        app(SettingService::class)->set('check_in_time', '14:00', 'string');
        app(SettingService::class)->set('cancellation_fee_percent', 50, 'integer');

        $this->roomType = RoomType::factory()->create(['default_inventory' => 2]);
        Room::factory()->count(2)->create(['room_type_id' => $this->roomType->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Check-in tomorrow at 14:00, i.e. 18 hours out: past the 24h free window
     * but comfortably before check-in — the middle tier.
     */
    private function bookingPastFreeWindow(): Booking
    {
        return $this->paidBooking(now()->addDay()->toDateString(), now()->addDays(3)->toDateString());
    }

    private function paidBooking(string $checkIn, string $checkOut, int $amount = 2_000_000): Booking
    {
        $booking = Booking::factory()->create([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'nights' => 2,
            'rooms' => 1,
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
            'total_amount' => $amount,
            'held_until' => null,
            'cancellation_policy' => ['free_cancellation_hours' => 24],
        ]);
        BookingItem::create([
            'booking_id' => $booking->id, 'room_type_id' => $this->roomType->id,
            'room_type_name' => $this->roomType->name, 'rooms' => 1, 'subtotal_amount' => $amount,
        ]);
        foreach ((new StayPeriod($checkIn, $checkOut))->stayDateStrings() as $date) {
            RoomTypeInventory::create([
                'room_type_id' => $this->roomType->id, 'inventory_date' => $date,
                'total_inventory' => 2, 'held_inventory' => 0, 'confirmed_inventory' => 1, 'blocked_inventory' => 0,
            ]);
        }
        PaymentAttempt::create([
            'booking_id' => $booking->id, 'provider' => 'doku',
            'invoice_number' => $booking->code.'-1', 'request_id' => 'req-'.$booking->id,
            'amount' => $amount, 'currency' => 'IDR', 'status' => PaymentAttemptStatus::PAID, 'paid_at' => now(),
        ]);

        return $booking->fresh('items');
    }

    public function test_cancelling_inside_the_free_window_refunds_in_full(): void
    {
        $booking = $this->paidBooking(now()->addDays(10)->toDateString(), now()->addDays(12)->toDateString());

        $request = app(CancellationService::class)->cancel($booking, 'Batal jauh hari');

        $this->assertSame(2_000_000, $request->refund_estimate);

        $refund = Refund::where('booking_id', $booking->id)->first();
        $this->assertNotNull($refund, 'A refund should be raised automatically.');
        $this->assertSame(2_000_000, $refund->amount);
        $this->assertSame(RefundStatus::REQUESTED, $refund->status);
        // Raised, not yet paid: booking is REFUND_PENDING until an admin completes it.
        $this->assertSame(PaymentStatus::REFUND_PENDING, $booking->fresh()->payment_status);
    }

    public function test_cancelling_after_the_window_keeps_the_configured_fee(): void
    {
        $booking = $this->bookingPastFreeWindow();

        $request = app(CancellationService::class)->cancel($booking, 'Batal mendadak');

        // 50% fee on 2,000,000 → refund 1,000,000.
        $this->assertSame(1_000_000, $request->refund_estimate);
        $this->assertSame(1_000_000, Refund::where('booking_id', $booking->id)->value('amount'));
    }

    public function test_the_fee_percentage_is_configurable(): void
    {
        app(SettingService::class)->set('cancellation_fee_percent', 25, 'integer');
        $booking = $this->bookingPastFreeWindow();

        $request = app(CancellationService::class)->cancel($booking, 'Batal mendadak');

        // 25% fee → refund 1,500,000.
        $this->assertSame(1_500_000, $request->refund_estimate);
    }

    public function test_an_unpaid_cancellation_raises_no_refund(): void
    {
        $booking = Booking::factory()->create([
            'check_in_date' => now()->addDays(10)->toDateString(),
            'check_out_date' => now()->addDays(12)->toDateString(),
            'status' => BookingStatus::HELD,
            'payment_status' => PaymentStatus::UNPAID,
            'held_until' => now()->addMinutes(20),
        ]);
        BookingItem::create([
            'booking_id' => $booking->id, 'room_type_id' => $this->roomType->id,
            'room_type_name' => $this->roomType->name, 'rooms' => 1, 'subtotal_amount' => 2_000_000,
        ]);
        foreach ((new StayPeriod($booking->check_in_date->toDateString(), $booking->check_out_date->toDateString()))->stayDateStrings() as $date) {
            RoomTypeInventory::create([
                'room_type_id' => $this->roomType->id, 'inventory_date' => $date,
                'total_inventory' => 2, 'held_inventory' => 1, 'confirmed_inventory' => 0, 'blocked_inventory' => 0,
            ]);
        }

        app(CancellationService::class)->cancel($booking->fresh('items'), 'Belum bayar');

        $this->assertSame(0, Refund::where('booking_id', $booking->id)->count());
    }

    public function test_a_hundred_percent_fee_refunds_nothing_and_raises_no_record(): void
    {
        app(SettingService::class)->set('cancellation_fee_percent', 100, 'integer');
        $booking = $this->bookingPastFreeWindow();

        $request = app(CancellationService::class)->cancel($booking, 'Batal, hangus');

        $this->assertSame(0, $request->refund_estimate);
        $this->assertSame(0, Refund::where('booking_id', $booking->id)->count());
        // Still REFUND_PENDING? No money is due, but the booking was paid — it
        // should not read as a completed refund either.
        $this->assertSame(PaymentStatus::REFUND_PENDING, $booking->fresh()->payment_status);
    }
}
