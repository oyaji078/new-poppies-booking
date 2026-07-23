<?php

namespace App\Console\Commands;

use App\Services\Booking\BookingExpirationService;
use Illuminate\Console\Command;

class ExpireBookingHolds extends Command
{
    protected $signature = 'bookings:expire-holds {--limit=200}';

    protected $description = 'Expire lapsed booking holds and release their inventory';

    public function handle(BookingExpirationService $service): int
    {
        $count = $service->expireDueHolds((int) $this->option('limit'));

        $this->info("Expired {$count} booking hold(s).");

        return self::SUCCESS;
    }
}
