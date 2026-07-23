<?php

namespace Tests\Feature\Operations;

use App\Services\Booking\BookingExpirationService;
use Tests\TestCase;

class ServerlessMaintenanceTest extends TestCase
{
    public function test_cron_endpoint_requires_the_configured_bearer_secret(): void
    {
        config(['services.vercel.cron_secret' => 'cron-test-secret']);

        $this->getJson('/cron/expire-booking-holds')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer wrong')
            ->getJson('/cron/expire-booking-holds')
            ->assertUnauthorized();
    }

    public function test_authorized_cron_expires_due_holds(): void
    {
        config(['services.vercel.cron_secret' => 'cron-test-secret']);
        $this->mock(BookingExpirationService::class)
            ->shouldReceive('expireDueHolds')
            ->once()
            ->andReturn(3);

        $this->withHeader('Authorization', 'Bearer cron-test-secret')
            ->getJson('/cron/expire-booking-holds')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'expired' => 3]);
    }
}
