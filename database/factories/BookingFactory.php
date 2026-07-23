<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $checkIn = now()->addDays(7)->startOfDay();
        $checkOut = (clone $checkIn)->addDays(2);

        return [
            'code' => 'NPS-'.$checkIn->format('Ymd').'-'.strtoupper(Str::random(6)),
            'user_id' => null,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->unique()->safeEmail(),
            'customer_phone' => fake()->numerify('08##########'),
            'customer_country' => 'Indonesia',
            'check_in_date' => $checkIn->toDateString(),
            'check_out_date' => $checkOut->toDateString(),
            'nights' => 2,
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
            'status' => BookingStatus::HELD,
            'payment_status' => PaymentStatus::UNPAID,
            'subtotal_amount' => 1_000_000,
            'discount_amount' => 0,
            'tax_amount' => 110_000,
            'service_amount' => 100_000,
            'total_amount' => 1_210_000,
            'currency' => 'IDR',
            'held_until' => now()->addMinutes(30),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
            'confirmed_at' => now(),
            'held_until' => null,
        ]);
    }

    public function pendingPayment(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::PENDING_PAYMENT, 'payment_status' => PaymentStatus::PENDING]);
    }

    public function expiredHold(): static
    {
        return $this->state(fn () => ['held_until' => now()->subMinute()]);
    }
}
