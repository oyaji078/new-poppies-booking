<?php

namespace Tests\Feature\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Models\User;
use App\Services\Operations\CancellationService;
use App\Services\Payments\PaymentReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A booking parked in PAYMENT_REVIEW after an amount mismatch is still holding
 * its rooms — the hold was never converted or released. Whatever the admin
 * decides next, those held units must be accounted for exactly once.
 */
class PaymentReviewInventoryTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $roomType;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->roomType = RoomType::factory()->create(['default_inventory' => 2]);
        Room::factory()->count(2)->create(['room_type_id' => $this->roomType->id]);
    }

    /**
     * A booking that reached review straight from PENDING_PAYMENT: its rooms are
     * still counted in held_inventory.
     */
    private function reviewBookingHoldingInventory(int $rooms = 1): Booking
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::PAYMENT_REVIEW,
            'payment_status' => PaymentStatus::REVIEW,
            'check_in_date' => today()->addDays(5)->toDateString(),
            'check_out_date' => today()->addDays(7)->toDateString(),
            'rooms' => $rooms,
            'held_until' => null,
            'review_inventory_held' => true,
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'room_type_id' => $this->roomType->id,
            'room_type_name' => $this->roomType->name,
            'rooms' => $rooms,
            'subtotal_amount' => 1_000_000,
        ]);

        foreach ($booking->stayPeriod()->stayDateStrings() as $date) {
            RoomTypeInventory::create([
                'room_type_id' => $this->roomType->id,
                'inventory_date' => $date,
                'total_inventory' => 2,
                'blocked_inventory' => 0,
                'held_inventory' => $rooms,
                'confirmed_inventory' => 0,
            ]);
        }

        return $booking->fresh('items');
    }

    /** @return array<string, array{held: int, confirmed: int}> */
    private function inventory(Booking $booking): array
    {
        return RoomTypeInventory::query()
            ->where('room_type_id', $this->roomType->id)
            ->whereIn('inventory_date', $booking->stayPeriod()->stayDateStrings())
            ->get()
            ->mapWithKeys(fn ($row) => [$row->inventory_date->toDateString() => [
                'held' => $row->held_inventory,
                'confirmed' => $row->confirmed_inventory,
            ]])
            ->all();
    }

    public function test_approving_converts_the_hold_instead_of_reserving_a_second_time(): void
    {
        $booking = $this->reviewBookingHoldingInventory();

        $this->actingAs($this->admin);
        app(PaymentReviewService::class)->approve($booking, 'Bukti transfer diverifikasi keuangan.');

        $this->assertSame(BookingStatus::CONFIRMED, $booking->fresh()->status);

        foreach ($this->inventory($booking) as $date => $counts) {
            $this->assertSame(0, $counts['held'], "held_inventory bocor pada {$date}");
            $this->assertSame(1, $counts['confirmed'], "confirmed_inventory salah pada {$date}");
        }
    }

    public function test_approving_the_last_room_is_not_blocked_by_the_booking_s_own_hold(): void
    {
        // 2 rooms total, this booking holds both. A capacity check that counts
        // the booking's own hold against it would refuse its own approval.
        $booking = $this->reviewBookingHoldingInventory(rooms: 2);

        $this->actingAs($this->admin);
        app(PaymentReviewService::class)->approve($booking, 'Selisih kurs, sudah dilunasi tamu.');

        $this->assertSame(BookingStatus::CONFIRMED, $booking->fresh()->status);

        foreach ($this->inventory($booking) as $counts) {
            $this->assertSame(0, $counts['held']);
            $this->assertSame(2, $counts['confirmed']);
        }
    }

    public function test_rejecting_returns_the_held_rooms_to_the_pool(): void
    {
        $booking = $this->reviewBookingHoldingInventory();

        $this->actingAs($this->admin);
        app(PaymentReviewService::class)->reject($booking, 'Pembayaran tidak dapat diverifikasi.');

        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);

        foreach ($this->inventory($booking) as $date => $counts) {
            $this->assertSame(0, $counts['held'], "held_inventory bocor pada {$date}");
            $this->assertSame(0, $counts['confirmed']);
        }
    }

    public function test_cancelling_a_reviewed_booking_returns_the_held_rooms_to_the_pool(): void
    {
        $booking = $this->reviewBookingHoldingInventory();

        app(CancellationService::class)->cancel($booking, 'Tamu membatalkan.', $this->admin, isAdmin: true);

        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);

        foreach ($this->inventory($booking) as $date => $counts) {
            $this->assertSame(0, $counts['held'], "held_inventory bocor pada {$date}");
            $this->assertSame(0, $counts['confirmed']);
        }
    }
}
