<?php

namespace App\Services\Booking;

use App\Enums\AuditAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\RoomType;
use App\Services\Audit\AuditLogger;
use App\Services\Pricing\PricingService;
use App\Services\Settings\SettingService;
use App\Support\StayPeriod;
use Carbon\CarbonImmutable;

/**
 * Creates booking holds. The whole hold — availability check, inventory
 * increment, booking, items and nightly price snapshots — happens inside ONE
 * transaction with the inventory rows locked, so two concurrent requests for the
 * last room can never both succeed (§9).
 */
class BookingService
{
    public function __construct(
        private readonly BookingInventoryService $inventory,
        private readonly PricingService $pricing,
        private readonly BookingCodeGenerator $codes,
        private readonly SettingService $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Validate the requested stay against policy (dates, nights, capacity).
     */
    public function assertStayIsBookable(RoomType $roomType, StayPeriod $stay, int $rooms, int $adults, int $children): void
    {
        $today = CarbonImmutable::today();

        if ($stay->checkIn->lt($today)) {
            throw BookingException::invalidStay('Tanggal check-in tidak boleh sebelum hari ini.');
        }

        if ($rooms < 1) {
            throw BookingException::invalidStay('Jumlah kamar minimal 1.');
        }

        $maxNights = $this->settings->integer('booking_max_nights', 30);
        if ($stay->nights() > $maxNights) {
            throw BookingException::invalidStay("Lama menginap maksimal {$maxNights} malam.");
        }

        $guestsPerRoom = (int) ceil(($adults + $children) / max(1, $rooms));
        if ($guestsPerRoom > $roomType->max_guests) {
            throw BookingException::capacityExceeded();
        }
    }

    /**
     * Create a 30-minute hold. Throws BookingException when unavailable.
     *
     * @param  array{
     *     customer_name: string, customer_email: string, customer_phone: string,
     *     customer_country?: ?string, special_request?: ?string, arrival_time?: ?string,
     *     guest_names?: array<int, string>, promo_code?: ?string, user_id?: ?int
     * }  $details
     */
    public function createHold(
        RoomType $roomType,
        StayPeriod $stay,
        int $rooms,
        int $adults,
        int $children,
        array $details,
    ): Booking {
        $this->assertStayIsBookable($roomType, $stay, $rooms, $adults, $children);

        $holdMinutes = $this->settings->integer('booking_hold_minutes', 30);
        $freeCancelHours = $this->settings->integer('free_cancellation_hours', 24);

        $booking = $this->inventory->transactionWithRetry(function () use (
            $roomType, $stay, $rooms, $adults, $children, $details, $holdMinutes, $freeCancelHours
        ) {
            // 1-4) Lock every stay night, then verify capacity under the lock.
            $lockedRows = $this->inventory->lockRows($roomType, $stay);
            $sellableCap = $roomType->sellableRoomCount();

            if (! $this->inventory->hasCapacity($lockedRows, $stay, $rooms, $sellableCap)) {
                throw BookingException::unavailable();
            }

            // 5) Price is recomputed server-side — never taken from the client.
            $quote = $this->pricing->quote(
                $roomType, $stay, $rooms, $adults, $children, $details['promo_code'] ?? null
            );

            // 6) Reserve the inventory.
            $this->inventory->increaseHeld($lockedRows, $rooms);

            // 7) Create the booking.
            $booking = Booking::create([
                'code' => $this->codes->generate(),
                'user_id' => $details['user_id'] ?? null,
                'customer_name' => $details['customer_name'],
                'customer_email' => $details['customer_email'],
                'customer_phone' => $details['customer_phone'],
                'customer_country' => $details['customer_country'] ?? null,
                'check_in_date' => $stay->checkIn->toDateString(),
                'check_out_date' => $stay->checkOut->toDateString(),
                'nights' => $stay->nights(),
                'rooms' => $rooms,
                'adults' => $adults,
                'children' => $children,
                'status' => BookingStatus::HELD,
                'payment_status' => PaymentStatus::UNPAID,
                'subtotal_amount' => $quote->subtotalBeforeDiscount,
                'discount_amount' => $quote->discountTotal,
                'tax_amount' => $quote->taxTotal,
                'service_amount' => $quote->serviceTotal,
                'total_amount' => $quote->grandTotal,
                'currency' => $quote->currency,
                'promotion_id' => $quote->promotion?->id,
                'promotion_code' => $quote->promotion?->code,
                'special_request' => $details['special_request'] ?? null,
                'arrival_time' => $details['arrival_time'] ?? null,
                'cancellation_policy' => [
                    'free_cancellation_hours' => $freeCancelHours,
                    'described' => "Pembatalan gratis hingga {$freeCancelHours} jam sebelum check-in.",
                    'snapshot_at' => now()->toIso8601String(),
                ],
                'held_until' => now()->addMinutes($holdMinutes),
            ]);

            // 8) Booking item (one room type per booking in this version).
            $item = BookingItem::create([
                'booking_id' => $booking->id,
                'room_type_id' => $roomType->id,
                'room_type_name' => $roomType->name,
                'rooms' => $rooms,
                'subtotal_amount' => $quote->subtotalBeforeDiscount,
            ]);

            // 9) Immutable nightly price snapshots.
            foreach ($quote->nightSnapshots() as $night) {
                $item->nights()->create($night);
            }

            // Guest names (primary guest defaults to the booker).
            $guestNames = array_values(array_filter($details['guest_names'] ?? []));
            if ($guestNames === []) {
                $guestNames = [$details['customer_name']];
            }
            foreach ($guestNames as $i => $name) {
                $booking->guests()->create(['full_name' => $name, 'is_primary' => $i === 0]);
            }

            // Consume promotion quota.
            if ($quote->promotion) {
                $quote->promotion->increment('used_count');
            }

            return $booking;
        });

        $this->audit->log(AuditAction::BOOKING_HOLD_CREATED->value, $booking, null, [
            'code' => $booking->code,
            'total_amount' => $booking->total_amount,
            'held_until' => $booking->held_until?->toIso8601String(),
        ]);

        return $booking->load('items.nights', 'guests');
    }
}
