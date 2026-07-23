<?php

namespace Tests\Feature\Doku;

use App\Enums\BookingStatus;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\RoomType;
use App\Services\Doku\DokuCheckoutService;
use App\Services\Doku\DokuSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DokuCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'BRN-0253-TEST';

    private const SECRET = 'SK-unit-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'doku.client_id' => self::CLIENT_ID,
            'doku.secret_key' => self::SECRET,
            'doku.base_url' => 'https://api-sandbox.doku.com',
            'doku.checkout_path' => '/checkout/v1/payment',
            'doku.payment_due_minutes' => 30,
            'doku.callback_url' => 'http://localhost/payment/callback',
        ]);
    }

    private function bookingWithSnapshots(int $total = 2_300_000): Booking
    {
        $roomType = RoomType::factory()->create(['base_price' => 1_000_000]);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::HELD,
            'total_amount' => $total,
            'nights' => 2,
            'rooms' => 1,
            'held_until' => now()->addMinutes(25),
        ]);

        $item = BookingItem::create([
            'booking_id' => $booking->id,
            'room_type_id' => $roomType->id,
            'room_type_name' => $roomType->name,
            'rooms' => 1,
            'subtotal_amount' => 2_000_000,
        ]);

        // Snapshots must reconcile to the stored total.
        $item->nights()->create([
            'stay_date' => $booking->check_in_date->toDateString(),
            'base_amount' => 1_000_000, 'adjustment_amount' => 0, 'discount_amount' => 0,
            'tax_amount' => 150_000, 'final_amount' => intdiv($total, 2),
        ]);
        $item->nights()->create([
            'stay_date' => $booking->check_in_date->copy()->addDay()->toDateString(),
            'base_amount' => 1_000_000, 'adjustment_amount' => 0, 'discount_amount' => 0,
            'tax_amount' => 150_000, 'final_amount' => $total - intdiv($total, 2),
        ]);

        return $booking->fresh(['items.nights']);
    }

    private function fakeSuccess(): void
    {
        Http::fake([
            '*/checkout/v1/payment' => Http::response([
                'message' => ['SUCCESS'],
                'response' => [
                    'order' => ['invoice_number' => 'INV', 'amount' => '2300000', 'session_id' => 'sess1'],
                    'payment' => [
                        'url' => 'https://sandbox.doku.com/checkout-link-v2/token123',
                        'token_id' => 'token123',
                        'expired_date' => '20260718104711',
                    ],
                ],
            ], 200),
        ]);
    }

    public function test_it_sends_a_correctly_signed_request_and_records_the_attempt(): void
    {
        $this->fakeSuccess();
        $booking = $this->bookingWithSnapshots();

        $attempt = app(DokuCheckoutService::class)->startPayment($booking);

        $this->assertSame(PaymentAttemptStatus::PENDING, $attempt->status);
        $this->assertSame(2_300_000, $attempt->amount);
        $this->assertSame('https://sandbox.doku.com/checkout-link-v2/token123', $attempt->payment_url);

        Http::assertSent(function ($request) {
            // Correct endpoint
            if ($request->url() !== 'https://api-sandbox.doku.com/checkout/v1/payment') {
                return false;
            }

            // Amount sent is the SERVER total, never a client value.
            $body = json_decode($request->body(), true);
            if (data_get($body, 'order.amount') !== 2_300_000) {
                return false;
            }

            // Signature must match a recomputation over the exact transmitted bytes.
            $signatures = new DokuSignatureService(self::CLIENT_ID, self::SECRET);
            $expected = $signatures->sign(
                $request->header('Request-Id')[0],
                $request->header('Request-Timestamp')[0],
                '/checkout/v1/payment',
                $signatures->digest($request->body()),
            );

            return $request->header('Client-Id')[0] === self::CLIENT_ID
                && $request->header('Signature')[0] === $expected;
        });
    }

    public function test_starting_payment_moves_booking_to_pending_payment(): void
    {
        $this->fakeSuccess();
        $booking = $this->bookingWithSnapshots();

        app(DokuCheckoutService::class)->startPayment($booking);

        $this->assertSame(BookingStatus::PENDING_PAYMENT, $booking->fresh()->status);
    }

    public function test_it_reuses_an_active_attempt_instead_of_creating_duplicates(): void
    {
        $this->fakeSuccess();
        $booking = $this->bookingWithSnapshots();
        $service = app(DokuCheckoutService::class);

        $first = $service->startPayment($booking);
        $second = $service->startPayment($booking->fresh(['items.nights']));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $booking->paymentAttempts()->count());
    }

    public function test_line_items_add_up_to_exactly_the_order_amount(): void
    {
        $this->fakeSuccess();
        $booking = $this->bookingWithSnapshots();

        app(DokuCheckoutService::class)->startPayment($booking);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            $lines = data_get($body, 'order.line_items', []);

            $sum = array_sum(array_map(
                fn ($line) => (int) $line['price'] * (int) $line['quantity'],
                $lines
            ));

            // A basket that does not reconcile with the amount is rejected by DOKU.
            return $lines !== [] && $sum === data_get($body, 'order.amount');
        });
    }

    public function test_payment_page_never_outlives_the_inventory_hold(): void
    {
        $this->fakeSuccess();
        $booking = $this->bookingWithSnapshots();
        // Only 10 minutes of hold left, while the configured window is 30.
        $booking->forceFill(['held_until' => now()->addMinutes(10)])->save();

        $attempt = app(DokuCheckoutService::class)->startPayment($booking->fresh(['items.nights']));

        Http::assertSent(function ($request) {
            $due = data_get(json_decode($request->body(), true), 'payment.payment_due_date');

            return $due <= 10 && $due >= 1;
        });

        $this->assertTrue($attempt->expires_at->lessThanOrEqualTo($booking->fresh()->held_until));
    }

    public function test_it_tells_doku_where_to_send_the_notification_when_the_url_is_public(): void
    {
        config(['doku.notification_url' => 'https://npseng.ngrok.io/api/payments/doku/notifications']);
        $this->fakeSuccess();

        app(DokuCheckoutService::class)->startPayment($this->bookingWithSnapshots());

        Http::assertSent(fn ($request) => data_get(json_decode($request->body(), true), 'additional_info.override_notification_url')
            === 'https://npseng.ngrok.io/api/payments/doku/notifications');
    }

    public function test_it_omits_the_notification_override_when_the_url_is_not_public(): void
    {
        // DOKU rejects an override it cannot reach, which would fail the checkout.
        config(['doku.notification_url' => 'http://localhost:8000/api/payments/doku/notifications']);
        $this->fakeSuccess();

        app(DokuCheckoutService::class)->startPayment($this->bookingWithSnapshots());

        Http::assertSent(fn ($request) => ! array_key_exists('additional_info', json_decode($request->body(), true)));
    }

    public function test_a_rejection_from_doku_surfaces_the_real_reason(): void
    {
        // The shape DOKU actually returns. Reporting "layanan pembayaran tidak
        // merespons" here would hide the one fact needed to fix the config.
        Http::fake([
            '*/checkout/v1/payment' => Http::response([
                'error' => ['code' => 'invalid_client_id', 'message' => 'Invalid Client-Id', 'type' => 'invalid_request_error'],
            ], 400),
        ]);

        $booking = $this->bookingWithSnapshots();

        try {
            app(DokuCheckoutService::class)->startPayment($booking);
            $this->fail('Expected the checkout to be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Invalid Client-Id', $e->getMessage());
            $this->assertStringContainsString('invalid_client_id', $e->getMessage());
        }
    }

    public function test_human_entered_text_is_coerced_into_the_characters_doku_accepts(): void
    {
        // DOKU rejects the whole checkout — naming no field — for anything outside
        // "a-z A-Z 0-9 . - / + , = _ : ' @ % ( )". Guest and room names are typed
        // by people, so accents, typographic dashes and emoji are routine.
        $this->fakeSuccess();
        $booking = $this->bookingWithSnapshots();
        $booking->forceFill(['customer_name' => 'José “Ana” Müller 🎉'])->save();
        $booking->items()->update(['room_type_name' => 'Deluxe – Sea View ★']);

        app(DokuCheckoutService::class)->startPayment($booking->fresh(['items.nights']));

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            $allowed = '/^[a-zA-Z0-9 .\-\/+,=_:\'@%()]*$/';

            $texts = [data_get($body, 'customer.name')];
            foreach (data_get($body, 'order.line_items', []) as $line) {
                $texts[] = $line['name'];
            }

            foreach ($texts as $text) {
                if (! preg_match($allowed, (string) $text)) {
                    return false;
                }
            }

            // Transliterated, not gutted: the name is still recognisable.
            return str_contains((string) data_get($body, 'customer.name'), 'Jose')
                && str_contains((string) data_get($body, 'customer.name'), 'Muller');
        });
    }

    public function test_a_doku_validation_error_surfaces_its_message(): void
    {
        // The shape DOKU uses for validation failures — distinct from the
        // {"error":{...}} shape used for auth failures.
        Http::fake([
            '*/checkout/v1/payment' => Http::response([
                'message' => ["Invalid character, allowed only a-z A-Z 0-9 . - / + , = _ : ' @ % ( )"],
            ], 400),
        ]);

        $this->expectExceptionMessageMatches('/Invalid character/');

        app(DokuCheckoutService::class)->startPayment($this->bookingWithSnapshots());
    }

    public function test_it_restricts_the_payment_methods_to_the_configured_list(): void
    {
        config(['doku.payment_method_types' => ['QRIS']]);
        $this->fakeSuccess();

        app(DokuCheckoutService::class)->startPayment($this->bookingWithSnapshots());

        Http::assertSent(fn ($request) => data_get(json_decode($request->body(), true), 'payment.payment_method_types') === ['QRIS']);
    }

    public function test_it_offers_every_method_when_none_are_configured(): void
    {
        config(['doku.payment_method_types' => []]);
        $this->fakeSuccess();

        app(DokuCheckoutService::class)->startPayment($this->bookingWithSnapshots());

        // Omitting the key lets DOKU show all methods enabled on the account.
        Http::assertSent(fn ($request) => ! array_key_exists('payment_method_types', data_get(json_decode($request->body(), true), 'payment', [])));
    }

    public function test_an_unknown_payment_method_is_dropped_rather_than_sent(): void
    {
        config([
            'doku.payment_method_types' => ['QRIS', 'BUKAN_METODE'],
            'doku.known_payment_method_types' => ['QRIS', 'CREDIT_CARD'],
        ]);
        $this->fakeSuccess();

        app(DokuCheckoutService::class)->startPayment($this->bookingWithSnapshots());

        Http::assertSent(fn ($request) => data_get(json_decode($request->body(), true), 'payment.payment_method_types') === ['QRIS']);
    }

    public function test_it_refuses_to_pay_an_expired_hold(): void
    {
        $this->fakeSuccess();
        $booking = $this->bookingWithSnapshots();
        $booking->forceFill(['held_until' => now()->subMinute()])->save();

        $this->expectException(BookingException::class);
        app(DokuCheckoutService::class)->startPayment($booking->fresh(['items.nights']));
    }

    public function test_it_refuses_when_snapshots_do_not_reconcile_with_the_total(): void
    {
        $this->fakeSuccess();
        $booking = $this->bookingWithSnapshots();
        // Tamper with the stored total so it no longer matches the snapshots.
        $booking->forceFill(['total_amount' => 1])->save();

        $this->expectException(\RuntimeException::class);
        app(DokuCheckoutService::class)->startPayment($booking->fresh(['items.nights']));
    }
}
