<?php

namespace Tests\Feature\Operations;

use App\Enums\BookingStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\PaymentAttempt;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Models\User;
use App\Services\Operations\CancellationService;
use App\Services\Operations\RefundService;
use App\Services\Settings\SettingService;
use App\Support\StayPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CancellationAndRefundTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingService::class)->set('free_cancellation_hours', 24, 'integer');
        app(SettingService::class)->set('check_in_time', '14:00', 'string');

        $this->roomType = RoomType::factory()->create(['default_inventory' => 2]);
        Room::factory()->count(2)->create(['room_type_id' => $this->roomType->id]);
    }

    private function booking(array $attrs = [], int $held = 0, int $confirmed = 0): Booking
    {
        $checkIn = now()->addDays(10)->toDateString();
        $checkOut = now()->addDays(12)->toDateString();

        $booking = Booking::factory()->create(array_merge([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'nights' => 2,
            'rooms' => 1,
            'total_amount' => 2_000_000,
            'cancellation_policy' => ['free_cancellation_hours' => 24],
        ], $attrs));

        BookingItem::create([
            'booking_id' => $booking->id,
            'room_type_id' => $this->roomType->id,
            'room_type_name' => $this->roomType->name,
            'rooms' => 1,
            'subtotal_amount' => 2_000_000,
        ]);

        foreach ((new StayPeriod($checkIn, $checkOut))->stayDateStrings() as $date) {
            RoomTypeInventory::create([
                'room_type_id' => $this->roomType->id, 'inventory_date' => $date,
                'total_inventory' => 2, 'held_inventory' => $held, 'confirmed_inventory' => $confirmed, 'blocked_inventory' => 0,
            ]);
        }

        return $booking->fresh('items');
    }

    private function paidAttempt(Booking $booking, int $amount = 2_000_000): PaymentAttempt
    {
        return PaymentAttempt::create([
            'booking_id' => $booking->id,
            'provider' => 'doku',
            'invoice_number' => $booking->code.'-1',
            'request_id' => 'req-'.$booking->id,
            'amount' => $amount,
            'currency' => 'IDR',
            'status' => PaymentAttemptStatus::PAID,
            'paid_at' => now(),
        ]);
    }

    public function test_held_booking_cancellation_releases_held_inventory(): void
    {
        $booking = $this->booking(['status' => BookingStatus::HELD], held: 1);

        app(CancellationService::class)->cancel($booking, 'Berubah rencana');

        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);
        $this->assertDatabaseHas('room_type_inventories', [
            'room_type_id' => $this->roomType->id, 'held_inventory' => 0,
        ]);
    }

    public function test_confirmed_booking_cancellation_releases_confirmed_inventory(): void
    {
        $booking = $this->booking([
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
            'held_until' => null,
        ], confirmed: 1);

        app(CancellationService::class)->cancel($booking, 'Perubahan jadwal');

        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);
        $this->assertDatabaseHas('room_type_inventories', [
            'room_type_id' => $this->roomType->id, 'confirmed_inventory' => 0,
        ]);
    }

    public function test_paid_cancellation_marks_refund_pending_not_refunded(): void
    {
        $booking = $this->booking([
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ], confirmed: 1);

        app(CancellationService::class)->cancel($booking, 'Dibatalkan tamu');

        // Cancellation is NOT a completed refund.
        $this->assertSame(PaymentStatus::REFUND_PENDING, $booking->fresh()->payment_status);
    }

    public function test_checked_out_booking_cannot_be_cancelled(): void
    {
        $booking = $this->booking(['status' => BookingStatus::CHECKED_OUT, 'held_until' => null]);

        $this->expectException(RuntimeException::class);
        app(CancellationService::class)->cancel($booking, 'Terlambat');
    }

    public function test_checked_in_booking_cannot_be_cancelled(): void
    {
        $booking = $this->booking(['status' => BookingStatus::CHECKED_IN, 'held_until' => null]);

        $this->expectException(RuntimeException::class);
        app(CancellationService::class)->cancel($booking, 'Berubah pikiran');
    }

    public function test_cancellation_requires_a_reason(): void
    {
        $booking = $this->booking(['status' => BookingStatus::HELD], held: 1);

        $this->expectException(RuntimeException::class);
        app(CancellationService::class)->cancel($booking, '   ');
    }

    public function test_refund_eligibility_respects_the_24_hour_window(): void
    {
        $service = app(CancellationService::class);

        $early = $this->booking([
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
            'check_in_date' => now()->addDays(10)->toDateString(),
            'check_out_date' => now()->addDays(12)->toDateString(),
        ], confirmed: 1);
        $this->assertTrue($service->isRefundEligible($early));

        $late = Booking::factory()->create([
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
            'check_in_date' => now()->addHours(3)->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'cancellation_policy' => ['free_cancellation_hours' => 24],
        ]);
        $this->assertFalse($service->isRefundEligible($late));
    }

    public function test_unpaid_booking_cannot_be_refunded(): void
    {
        $booking = $this->booking(['status' => BookingStatus::HELD, 'payment_status' => PaymentStatus::UNPAID], held: 1);

        $this->expectException(RuntimeException::class);
        app(RefundService::class)->request($booking, 100_000, 'Coba refund');
    }

    public function test_refund_cannot_exceed_amount_paid(): void
    {
        $booking = $this->booking([
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ], confirmed: 1);
        $this->paidAttempt($booking, 2_000_000);

        $this->expectException(RuntimeException::class);
        app(RefundService::class)->request($booking->fresh(), 2_500_000, 'Terlalu besar');
    }

    public function test_refund_is_not_complete_until_marked_succeeded(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = $this->booking([
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ], confirmed: 1);
        $this->paidAttempt($booking, 2_000_000);

        $service = app(RefundService::class);
        $refund = $service->request($booking->fresh(), 2_000_000, 'Pembatalan tamu');

        // Still pending — booking must not read as refunded yet.
        $this->assertSame(RefundStatus::REQUESTED, $refund->status);
        $this->assertNotSame(PaymentStatus::REFUNDED, $booking->fresh()->payment_status);

        $service->updateStatus($refund, RefundStatus::SUCCEEDED, $admin, 'Transfer manual');

        $this->assertSame(PaymentStatus::REFUNDED, $booking->fresh()->payment_status);
    }

    public function test_partial_refund_sets_partially_refunded(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = $this->booking([
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ], confirmed: 1);
        $this->paidAttempt($booking, 2_000_000);

        $service = app(RefundService::class);
        $refund = $service->request($booking->fresh(), 500_000, 'Refund sebagian');
        $service->updateStatus($refund, RefundStatus::SUCCEEDED, $admin);

        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $booking->fresh()->payment_status);
    }

    public function test_two_refunds_cannot_exceed_the_paid_amount_in_total(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = $this->booking([
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ], confirmed: 1);
        $this->paidAttempt($booking, 2_000_000);

        $service = app(RefundService::class);
        $first = $service->request($booking->fresh(), 1_500_000, 'Sebagian 1');
        $service->updateStatus($first, RefundStatus::SUCCEEDED, $admin);

        // Only 500,000 remains refundable.
        $this->expectException(RuntimeException::class);
        $service->request($booking->fresh(), 1_000_000, 'Sebagian 2');
    }
}
