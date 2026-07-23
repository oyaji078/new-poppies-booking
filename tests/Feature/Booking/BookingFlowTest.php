<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Livewire\Public\CheckoutWizard;
use App\Livewire\Public\RoomSearch;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Models\User;
use App\Services\Settings\SettingService;
use App\Support\StayPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $checkIn;

    private string $checkOut;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(SettingService::class);
        $settings->set('tax_percent', 10, 'integer');
        $settings->set('service_percent', 5, 'integer');
        $settings->set('weekend_surcharge_percent', 0, 'integer');
        $settings->set('extra_guest_fee', 0, 'integer');
        $settings->set('booking_hold_minutes', 30, 'integer');
        $settings->set('booking_max_nights', 30, 'integer');

        $this->checkIn = now()->addDays(10)->toDateString();
        $this->checkOut = now()->addDays(12)->toDateString();
    }

    private function roomType(int $units = 3): RoomType
    {
        $roomType = RoomType::factory()->create([
            'default_inventory' => $units, 'base_price' => 1_000_000,
            'adult_capacity' => 2, 'max_guests' => 4, 'is_published' => true,
        ]);
        Room::factory()->count($units)->create(['room_type_id' => $roomType->id]);

        foreach ((new StayPeriod($this->checkIn, $this->checkOut))->stayDateStrings() as $date) {
            RoomTypeInventory::create([
                'room_type_id' => $roomType->id, 'inventory_date' => $date,
                'total_inventory' => $units, 'held_inventory' => 0, 'confirmed_inventory' => 0, 'blocked_inventory' => 0,
            ]);
        }

        return $roomType;
    }

    public function test_search_page_renders_and_lists_available_room_type(): void
    {
        $roomType = $this->roomType();

        Livewire::test(RoomSearch::class, ['checkIn' => $this->checkIn, 'checkOut' => $this->checkOut])
            ->set('checkIn', $this->checkIn)
            ->set('checkOut', $this->checkOut)
            ->set('adults', 2)
            ->set('rooms', 1)
            ->assertOk()
            ->assertSee($roomType->name);
    }

    public function test_search_rejects_checkout_before_checkin(): void
    {
        $this->roomType();

        Livewire::test(RoomSearch::class)
            ->set('checkIn', now()->addDays(5)->toDateString())
            ->set('checkOut', now()->addDays(3)->toDateString())
            ->call('search')
            ->assertHasErrors('checkOut');
    }

    public function test_checkout_creates_a_hold_and_redirects_to_booking(): void
    {
        $roomType = $this->roomType();

        Livewire::withQueryParams([
            'checkin' => $this->checkIn, 'checkout' => $this->checkOut,
            'adults' => 2, 'children' => 0, 'rooms' => 1,
        ])
            ->test(CheckoutWizard::class, ['roomType' => $roomType])
            ->set('customer_name', 'Budi Santoso')
            ->set('customer_email', 'budi@example.com')
            ->set('customer_phone', '081234567890')
            ->set('terms', true)
            ->call('goToReview')
            ->assertHasNoErrors()
            ->call('confirmBooking')
            ->assertRedirect();

        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertSame(BookingStatus::HELD, $booking->status);
        $this->assertSame('budi@example.com', $booking->customer_email);
        $this->assertSame(2_300_000, $booking->total_amount);
    }

    public function test_checkout_requires_terms_acceptance(): void
    {
        $roomType = $this->roomType();

        Livewire::withQueryParams(['checkin' => $this->checkIn, 'checkout' => $this->checkOut])
            ->test(CheckoutWizard::class, ['roomType' => $roomType])
            ->set('customer_name', 'Budi')
            ->set('customer_email', 'budi@example.com')
            ->set('customer_phone', '0812')
            ->set('terms', false)
            ->call('goToReview')
            ->assertHasErrors('terms');
    }

    public function test_booking_details_require_code_and_matching_email(): void
    {
        $booking = Booking::factory()->create(['customer_email' => 'owner@example.com']);

        // Wrong email is rejected...
        $this->post(route('booking.lookup'), ['code' => $booking->code, 'email' => 'attacker@example.com'])
            ->assertSessionHasErrors('code');

        // ...and the code alone never grants access.
        $this->get(route('booking.show', $booking->code))->assertForbidden();
    }

    public function test_correct_code_and_email_grants_access(): void
    {
        $booking = Booking::factory()->create(['customer_email' => 'owner@example.com']);

        $this->post(route('booking.lookup'), ['code' => $booking->code, 'email' => 'owner@example.com'])
            ->assertRedirect(route('booking.show', $booking->code));

        $this->get(route('booking.show', $booking->code))
            ->assertOk()
            ->assertSee($booking->code);
    }

    public function test_owner_can_view_their_booking_without_lookup(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('booking.show', $booking->code))->assertOk();
    }

    public function test_another_logged_in_user_cannot_view_someone_elses_booking(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)->get(route('booking.show', $booking->code))->assertForbidden();
    }
}
