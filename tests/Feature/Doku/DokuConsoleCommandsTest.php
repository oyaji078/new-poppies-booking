<?php

namespace Tests\Feature\Doku;

use App\Enums\BookingStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Services\Doku\DokuEnvironmentService;
use App\Services\Doku\DokuNotificationVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The DOKU setup commands are only worth having if their verdicts are true, so
 * each check is asserted against a configuration that should fail it, and the
 * simulated notification is asserted to pass the REAL verifier.
 */
class DokuConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'BRN-0253-TEST';

    private const SECRET = 'SK-unit-test-secret-value';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'doku.environment' => 'sandbox',
            'doku.client_id' => self::CLIENT_ID,
            'doku.secret_key' => self::SECRET,
            'doku.base_url' => 'https://api-sandbox.doku.com',
            'doku.notification_url' => 'https://npseng.ngrok.io/api/payments/doku/notifications',
            'doku.callback_url' => 'https://npseng.ngrok.io/payment/callback',
            'doku.payment_due_minutes' => 30,
        ]);
    }

    public function test_check_passes_on_a_sound_configuration(): void
    {
        $this->artisan('doku:check')->assertSuccessful();
    }

    public function test_check_fails_when_doku_cannot_reach_the_notification_url(): void
    {
        config(['doku.notification_url' => 'http://localhost:8000/api/payments/doku/notifications']);

        $this->artisan('doku:check')->assertFailed();
    }

    public function test_check_fails_when_sandbox_credentials_point_at_production(): void
    {
        config(['doku.base_url' => 'https://api.doku.com']);

        $this->artisan('doku:check')->assertFailed();
    }

    public function test_check_fails_when_the_payment_window_outlives_the_hold(): void
    {
        // Hold is seeded at 30 minutes; a 45-minute payment page would survive it.
        config(['doku.payment_due_minutes' => 45]);

        $this->artisan('doku:check')->assertFailed();
    }

    public function test_check_fails_when_production_doku_runs_with_debug_enabled(): void
    {
        // An internet-reachable Laravel with APP_DEBUG=true publishes the DOKU
        // secret key on any stack trace.
        config([
            'doku.environment' => 'production',
            'doku.base_url' => 'https://api.doku.com',
            'app.debug' => true,
        ]);

        $this->artisan('doku:check')->assertFailed();

        config(['app.debug' => false]);
        $this->artisan('doku:check')->assertSuccessful();
    }

    public function test_check_can_preview_another_environment_without_switching(): void
    {
        config([
            'doku.default_environment' => 'production',
            'doku.environments.sandbox.client_id' => 'BRN-SANDBOX-1',
            'doku.environments.sandbox.secret_key' => 'SK-sandbox-secret-value',
            'doku.environments.sandbox.base_url' => 'https://api-sandbox.doku.com',
            'doku.environments.production.client_id' => 'BRN-PROD-1',
            'doku.environments.production.secret_key' => 'SK-production-secret-value',
            'doku.environments.production.base_url' => 'https://api.doku.com',
            'doku.environment' => 'production',
            'doku.base_url' => 'https://api.doku.com',
            'app.debug' => false,
        ]);

        $this->artisan('doku:check --environment=sandbox')
            ->expectsOutputToContain('SANDBOX')
            ->assertSuccessful();

        // Previewing must not move the stored active mode.
        $this->assertSame('production', app(DokuEnvironmentService::class)->active());
    }

    public function test_check_rejects_an_unknown_environment(): void
    {
        $this->artisan('doku:check --environment=staging')->assertFailed();
    }

    public function test_simulated_notification_passes_the_real_signature_verifier(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::PENDING_PAYMENT,
            'total_amount' => 1_500_000,
            'held_until' => now()->addMinutes(20),
        ]);

        $attempt = PaymentAttempt::create([
            'booking_id' => $booking->id,
            'provider' => 'doku',
            'invoice_number' => $booking->code.'-1',
            'request_id' => 'req-initial',
            'amount' => 1_500_000,
            'currency' => 'IDR',
            'payment_url' => 'https://sandbox.doku.com/checkout-link-v2/abc',
            'status' => PaymentAttemptStatus::PENDING,
            'expires_at' => now()->addMinutes(20),
        ]);

        Http::fake(['*' => Http::response(['message' => 'OK'], 200)]);

        $this->artisan('doku:simulate-notification', ['invoice' => $attempt->invoice_number])
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            // Rebuild the inbound request exactly as the webhook would see it and
            // run it through the production verifier.
            $inbound = Request::create(
                'https://npseng.ngrok.io/api/payments/doku/notifications',
                'POST',
                [], [], [],
                [
                    'HTTP_Client-Id' => $request->header('Client-Id')[0],
                    'HTTP_Request-Id' => $request->header('Request-Id')[0],
                    'HTTP_Request-Timestamp' => $request->header('Request-Timestamp')[0],
                    'HTTP_Signature' => $request->header('Signature')[0],
                ],
                $request->body(),
            );

            return app(DokuNotificationVerifier::class)->verify($inbound)['valid'] === true;
        });
    }

    public function test_simulated_notification_can_forge_an_invalid_signature_on_purpose(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::PENDING_PAYMENT, 'total_amount' => 1_000]);

        PaymentAttempt::create([
            'booking_id' => $booking->id,
            'provider' => 'doku',
            'invoice_number' => $booking->code.'-1',
            'request_id' => 'req-initial',
            'amount' => 1_000,
            'currency' => 'IDR',
            'payment_url' => 'https://sandbox.doku.com/checkout-link-v2/abc',
            'status' => PaymentAttemptStatus::PENDING,
        ]);

        Http::fake(['*' => Http::response(['message' => 'Invalid signature'], 401)]);

        $this->artisan('doku:simulate-notification', [
            'invoice' => $booking->code.'-1',
            '--invalid-signature' => true,
        ])->assertSuccessful();

        // The header is present but deliberately not a real HMAC, so the webhook
        // exercises its 401 path.
        Http::assertSent(function ($request) {
            $inbound = Request::create(
                'https://npseng.ngrok.io/api/payments/doku/notifications',
                'POST',
                [], [], [],
                [
                    'HTTP_Client-Id' => $request->header('Client-Id')[0],
                    'HTTP_Request-Id' => $request->header('Request-Id')[0],
                    'HTTP_Request-Timestamp' => $request->header('Request-Timestamp')[0],
                    'HTTP_Signature' => $request->header('Signature')[0],
                ],
                $request->body(),
            );

            return app(DokuNotificationVerifier::class)->verify($inbound)['valid'] === false;
        });
    }

    public function test_simulate_reports_an_unknown_invoice(): void
    {
        $this->artisan('doku:simulate-notification', ['invoice' => 'TIDAK-ADA'])->assertFailed();
    }
}
