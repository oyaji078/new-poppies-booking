<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\RoomType;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function bookingWithItem(): Booking
    {
        $roomType = RoomType::factory()->create(['name' => 'Deluxe Room']);
        $booking = Booking::factory()->create([
            'customer_email' => 'owner@example.com',
            'customer_name' => 'Ibu Sri',
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
            'subtotal_amount' => 977_500,
            'tax_amount' => 107_525,
            'service_amount' => 97_750,
            'total_amount' => 1_182_775,
        ]);
        BookingItem::create([
            'booking_id' => $booking->id,
            'room_type_id' => $roomType->id,
            'room_type_name' => $roomType->name,
            'rooms' => 1,
            'subtotal_amount' => 977_500,
        ]);

        return $booking;
    }

    public function test_it_renders_a_branded_invoice_with_the_booking_figures(): void
    {
        app(SettingService::class)
            ->set('hotel_name', 'New Poppies Senggigi', 'string', 'hotel', 'Nama Hotel', true);

        $booking = $this->bookingWithItem();

        $this->withSession(['booking_access.'.$booking->code => 'owner@example.com'])
            ->get(route('booking.invoice', $booking->code))
            ->assertOk()
            // The word is uppercased by CSS; the HTML text itself is "Invoice".
            ->assertSee('Invoice')
            ->assertSee($booking->code)
            ->assertSee('Ibu Sri')
            ->assertSee('Deluxe Room')
            ->assertSee('New Poppies Senggigi')      // brand identity from settings
            ->assertSee('LUNAS')                       // paid stamp
            ->assertSee(rupiah(1_182_775));            // grand total
    }

    public function test_invoice_is_closed_to_someone_without_access(): void
    {
        $booking = $this->bookingWithItem();

        $this->get(route('booking.invoice', $booking->code))->assertForbidden();
    }

    public function test_an_unpaid_invoice_shows_the_awaiting_payment_note_not_the_paid_stamp(): void
    {
        $roomType = RoomType::factory()->create();
        $booking = Booking::factory()->create([
            'customer_email' => 'owner@example.com',
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PENDING,
        ]);
        BookingItem::create([
            'booking_id' => $booking->id,
            'room_type_id' => $roomType->id,
            'room_type_name' => $roomType->name,
            'rooms' => 1,
            'subtotal_amount' => 500_000,
        ]);

        $this->withSession(['booking_access.'.$booking->code => 'owner@example.com'])
            ->get(route('booking.invoice', $booking->code))
            ->assertOk()
            ->assertSee('Menunggu Pembayaran')
            ->assertDontSee('LUNAS');
    }
}
