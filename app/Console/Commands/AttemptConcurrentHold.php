<?php

namespace App\Console\Commands;

use App\Exceptions\BookingException;
use App\Models\RoomType;
use App\Services\Booking\BookingService;
use App\Support\StayPeriod;
use Illuminate\Console\Command;
use Throwable;

/**
 * Test harness command: attempts one booking hold and reports the outcome.
 *
 * Used by the concurrency test, which launches several of these as SEPARATE OS
 * processes so the database really does see simultaneous transactions. It is
 * deliberately side-effect-free beyond creating the hold it is asked to create.
 */
class AttemptConcurrentHold extends Command
{
    protected $signature = 'booking:attempt-hold
        {roomType : Room type id}
        {checkIn : Y-m-d}
        {checkOut : Y-m-d}
        {email : Customer email (makes each attempt distinguishable)}
        {--rooms=1}
        {--startAt= : Unix timestamp (with microseconds) to synchronise the attempt}';

    protected $description = 'Attempt a single booking hold (concurrency test harness)';

    public function handle(BookingService $bookings): int
    {
        // Busy-wait until the agreed start moment so every process fires together.
        if ($startAt = $this->option('startAt')) {
            $target = (float) $startAt;
            while (microtime(true) < $target) {
                usleep(200);
            }
        }

        $roomType = RoomType::find((int) $this->argument('roomType'));
        if (! $roomType) {
            $this->line('ERROR:room_type_not_found');

            return self::FAILURE;
        }

        try {
            $booking = $bookings->createHold(
                $roomType,
                new StayPeriod($this->argument('checkIn'), $this->argument('checkOut')),
                (int) $this->option('rooms'),
                2,
                0,
                [
                    'customer_name' => 'Concurrency Tester',
                    'customer_email' => $this->argument('email'),
                    'customer_phone' => '0810000000',
                ],
            );

            $this->line('SUCCESS:'.$booking->code);

            return self::SUCCESS;
        } catch (BookingException $e) {
            $this->line('REJECTED:'.$e->getMessage());

            return self::SUCCESS; // a rejection is a valid, expected outcome
        } catch (Throwable $e) {
            $this->line('ERROR:'.$e->getMessage());

            return self::FAILURE;
        }
    }
}
