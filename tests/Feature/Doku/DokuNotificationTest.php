<?php

namespace Tests\Feature\Doku;

use App\Enums\BookingStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Services\Doku\DokuSignatureService;
use App\Support\StayPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DokuNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'BRN-0253-TEST';

    private const SECRET = 'SK-unit-test-secret';

    private const PATH = '/webhook/doku/notifications';

    private RoomType $roomType;

    private Booking $booking;

    private PaymentAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        config([
            'doku.client_id' => self::CLIENT_ID,
            'doku.secret_key' => self::SECRET,
            'doku.notification_url' => 'http://localhost'.self::PATH,
        ]);

        $checkIn = now()->addDays(10)->toDateString();
        $checkOut = now()->addDays(12)->toDateString();

        $this->roomType = RoomType::factory()->create(['default_inventory' => 2, 'base_price' => 1_000_000]);
        Room::factory()->count(2)->create(['room_type_id' => $this->roomType->id]);

        foreach ((new StayPeriod($checkIn, $checkOut))->stayDateStrings() as $date) {
            RoomTypeInventory::create([
                'room_type_id' => $this->roomType->id, 'inventory_date' => $date,
                'total_inventory' => 2, 'held_inventory' => 1, 'confirmed_inventory' => 0, 'blocked_inventory' => 0,
            ]);
        }

        $this->booking = Booking::factory()->create([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'nights' => 2,
            'rooms' => 1,
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
            'total_amount' => 2_300_000,
            'held_until' => now()->addMinutes(20),
        ]);

        BookingItem::create([
            'booking_id' => $this->booking->id,
            'room_type_id' => $this->roomType->id,
            'room_type_name' => $this->roomType->name,
            'rooms' => 1,
            'subtotal_amount' => 2_000_000,
        ]);

        $this->attempt = PaymentAttempt::create([
            'booking_id' => $this->booking->id,
            'provider' => 'doku',
            'invoice_number' => $this->booking->code.'-1',
            'request_id' => 'req-initial',
            'amount' => 2_300_000,
            'currency' => 'IDR',
            'payment_url' => 'https://sandbox.doku.com/checkout-link-v2/abc',
            'status' => PaymentAttemptStatus::PENDING,
            'expires_at' => now()->addMinutes(20),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(string $status = 'SUCCESS', ?int $amount = null, ?string $invoice = null): array
    {
        return [
            'service' => ['id' => 'VIRTUAL_ACCOUNT'],
            'acquirer' => ['id' => 'BCA'],
            'channel' => ['id' => 'VIRTUAL_ACCOUNT_BCA'],
            'order' => [
                'invoice_number' => $invoice ?? $this->attempt->invoice_number,
                'amount' => $amount ?? 2_300_000,
                'currency' => 'IDR',
            ],
            'transaction' => [
                'status' => $status,
                'date' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Post a notification signed exactly the way DOKU signs it.
     */
    private function postSigned(
        array $payload,
        ?string $requestId = null,
        bool $validSignature = true,
        string $path = self::PATH,
    ) {
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $requestId ??= 'req-'.uniqid();
        $timestamp = '2026-07-18T08:45:42Z';

        $signatures = new DokuSignatureService(self::CLIENT_ID, self::SECRET);
        $digest = $signatures->digest($raw);
        $signature = $validSignature
            ? $signatures->sign($requestId, $timestamp, $path, $digest)
            : 'HMACSHA256=forged-signature-value';

        return $this->call(
            'POST',
            $path,
            [], [], [],
            [
                'HTTP_Client-Id' => self::CLIENT_ID,
                'HTTP_Request-Id' => $requestId,
                'HTTP_Request-Timestamp' => $timestamp,
                'HTTP_Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $raw
        );
    }

    public function test_valid_success_notification_confirms_booking_and_converts_inventory(): void
    {
        $this->postSigned($this->payload())->assertOk();

        $this->booking->refresh();
        $this->assertSame(BookingStatus::CONFIRMED, $this->booking->status);
        $this->assertSame(PaymentStatus::PAID, $this->booking->payment_status);
        $this->assertNotNull($this->booking->confirmed_at);
        $this->assertSame(PaymentAttemptStatus::PAID, $this->attempt->refresh()->status);

        // Held inventory became confirmed on every stay night.
        $this->assertDatabaseHas('room_type_inventories', [
            'room_type_id' => $this->roomType->id, 'held_inventory' => 0, 'confirmed_inventory' => 1,
        ]);

        Mail::assertQueued(BookingConfirmedMail::class, 1);
    }

    public function test_duplicate_notification_does_not_confirm_twice_or_resend_email(): void
    {
        $payload = $this->payload();

        $this->postSigned($payload, 'req-same')->assertOk();
        $second = $this->postSigned($payload, 'req-same')->assertOk();

        $this->assertSame('duplicate', $second->json('result'));

        // Inventory changed exactly once.
        $this->assertDatabaseHas('room_type_inventories', [
            'room_type_id' => $this->roomType->id, 'held_inventory' => 0, 'confirmed_inventory' => 1,
        ]);
        // And only one confirmation email.
        Mail::assertQueued(BookingConfirmedMail::class, 1);
        $this->assertSame(1, PaymentEvent::count());
    }

    public function test_invalid_signature_is_rejected_and_changes_nothing(): void
    {
        $this->postSigned($this->payload(), validSignature: false)->assertStatus(401);

        $this->booking->refresh();
        $this->assertSame(BookingStatus::PENDING_PAYMENT, $this->booking->status);
        $this->assertSame(PaymentStatus::PENDING, $this->booking->payment_status);

        // Inventory untouched.
        $this->assertDatabaseHas('room_type_inventories', [
            'room_type_id' => $this->roomType->id, 'held_inventory' => 1, 'confirmed_inventory' => 0,
        ]);
        Mail::assertNothingQueued();

        // The rejection is recorded for the security log.
        $this->assertDatabaseHas('payment_events', ['signature_valid' => false, 'processing_status' => 'failed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.signature_invalid']);
    }

    public function test_wrong_client_id_is_rejected(): void
    {
        $raw = json_encode($this->payload(), JSON_UNESCAPED_SLASHES);

        $this->call('POST', self::PATH, [], [], [], [
            'HTTP_Client-Id' => 'SOMEONE-ELSE',
            'HTTP_Request-Id' => 'req-x',
            'HTTP_Request-Timestamp' => '2026-07-18T08:45:42Z',
            'HTTP_Signature' => 'HMACSHA256=whatever',
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertStatus(401);

        $this->assertSame(BookingStatus::PENDING_PAYMENT, $this->booking->refresh()->status);
    }

    public function test_notification_without_signature_headers_is_rejected_without_writing_anything(): void
    {
        $raw = json_encode($this->payload(), JSON_UNESCAPED_SLASHES);

        // No Client-Id / Request-Id / Signature at all: cannot be DOKU, and carries
        // no idempotency key, so it must be turned away before touching the DB.
        $this->call('POST', self::PATH, [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw)
            ->assertStatus(400);

        $this->assertSame(0, PaymentEvent::count());
        $this->assertSame(BookingStatus::PENDING_PAYMENT, $this->booking->refresh()->status);
    }

    public function test_amount_mismatch_routes_to_payment_review_and_never_confirms(): void
    {
        $this->postSigned($this->payload(amount: 100_000))->assertOk();

        $this->booking->refresh();
        $this->assertSame(BookingStatus::PAYMENT_REVIEW, $this->booking->status);
        $this->assertSame(PaymentStatus::REVIEW, $this->booking->payment_status);

        // Inventory NOT converted.
        $this->assertDatabaseHas('room_type_inventories', [
            'room_type_id' => $this->roomType->id, 'held_inventory' => 1, 'confirmed_inventory' => 0,
        ]);
        Mail::assertNothingQueued();
    }

    public function test_unknown_invoice_returns_404(): void
    {
        $this->postSigned($this->payload(invoice: 'NOPE-999'))->assertStatus(404);
    }

    public function test_failed_payment_keeps_booking_alive_for_retry(): void
    {
        $this->postSigned($this->payload('FAILED'))->assertOk();

        $this->booking->refresh();
        $this->assertSame(BookingStatus::PENDING_PAYMENT, $this->booking->status);
        $this->assertSame(PaymentAttemptStatus::FAILED, $this->attempt->refresh()->status);
        // Hold still valid, so inventory remains reserved for the retry.
        $this->assertDatabaseHas('room_type_inventories', [
            'room_type_id' => $this->roomType->id, 'held_inventory' => 1,
        ]);
    }

    public function test_unrecognised_status_never_confirms_and_goes_to_review(): void
    {
        $this->postSigned($this->payload('SOMETHING_NEW'))->assertOk();

        $this->booking->refresh();
        $this->assertSame(BookingStatus::PAYMENT_REVIEW, $this->booking->status);
        $this->assertNotSame(PaymentStatus::PAID, $this->booking->payment_status);
    }

    public function test_late_payment_with_available_inventory_is_recovered(): void
    {
        // Hold lapsed and inventory was released back to the pool.
        $this->booking->forceFill([
            'status' => BookingStatus::EXPIRED,
            'payment_status' => PaymentStatus::EXPIRED,
            'held_until' => now()->subMinutes(5),
        ])->save();
        RoomTypeInventory::where('room_type_id', $this->roomType->id)
            ->update(['held_inventory' => 0, 'confirmed_inventory' => 0]);

        $this->postSigned($this->payload())->assertOk();

        $this->booking->refresh();
        $this->assertSame(BookingStatus::CONFIRMED, $this->booking->status);
        $this->assertTrue($this->booking->late_payment_recovery);
        $this->assertDatabaseHas('room_type_inventories', [
            'room_type_id' => $this->roomType->id, 'confirmed_inventory' => 1,
        ]);
    }

    public function test_late_payment_without_inventory_goes_to_review(): void
    {
        $this->booking->forceFill([
            'status' => BookingStatus::EXPIRED,
            'payment_status' => PaymentStatus::EXPIRED,
            'held_until' => now()->subMinutes(5),
        ])->save();

        // Someone else took every room in the meantime.
        RoomTypeInventory::where('room_type_id', $this->roomType->id)
            ->update(['held_inventory' => 0, 'confirmed_inventory' => 2]);

        $this->postSigned($this->payload())->assertOk();

        $this->booking->refresh();
        $this->assertSame(BookingStatus::PAYMENT_REVIEW, $this->booking->status);
        $this->assertSame(PaymentStatus::REVIEW, $this->booking->payment_status);
        // Inventory never went negative / over-committed.
        $this->assertDatabaseHas('room_type_inventories', [
            'room_type_id' => $this->roomType->id, 'confirmed_inventory' => 2,
        ]);
        Mail::assertNothingQueued();
    }
}
