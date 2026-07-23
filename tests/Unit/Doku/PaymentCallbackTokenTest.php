<?php

namespace Tests\Unit\Doku;

use App\Models\Booking;
use App\Services\Doku\PaymentCallbackToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCallbackTokenTest extends TestCase
{
    use RefreshDatabase;

    private PaymentCallbackToken $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = new PaymentCallbackToken;
    }

    public function test_a_fresh_token_validates_for_its_own_booking(): void
    {
        $booking = Booking::factory()->create();

        $this->assertTrue($this->token->isValid($this->token->for($booking), $booking->code));
    }

    public function test_it_is_rejected_for_a_different_booking(): void
    {
        $a = Booking::factory()->create();
        $b = Booking::factory()->create();

        $this->assertFalse($this->token->isValid($this->token->for($a), $b->code));
    }

    public function test_a_tampered_or_empty_token_is_rejected(): void
    {
        $booking = Booking::factory()->create();
        $valid = $this->token->for($booking);

        $this->assertFalse($this->token->isValid(null, $booking->code));
        $this->assertFalse($this->token->isValid('', $booking->code));
        $this->assertFalse($this->token->isValid('not-a-token', $booking->code));
        $this->assertFalse($this->token->isValid($valid.'x', $booking->code));
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $booking = Booking::factory()->create();
        $token = $this->token->for($booking);

        $this->travel(4)->hours();

        // TTL is 180 minutes, so 4 hours later it must no longer validate.
        $this->assertFalse($this->token->isValid($token, $booking->code));
    }

    public function test_a_token_signed_with_a_different_key_is_rejected(): void
    {
        $booking = Booking::factory()->create();
        $token = $this->token->for($booking);

        // Rotating the app key must invalidate every outstanding token.
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->assertFalse($this->token->isValid($token, $booking->code));
    }
}
