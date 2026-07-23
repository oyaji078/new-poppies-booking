<?php

namespace Tests\Feature\Pricing;

use App\Models\Promotion;
use App\Models\RoomType;
use App\Models\SeasonalRate;
use App\Services\Pricing\PricingService;
use App\Services\Settings\SettingService;
use App\Support\StayPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(SettingService::class);
        $settings->set('weekend_surcharge_percent', 20, 'integer');
        $settings->set('extra_guest_fee', 100000, 'integer');
        $settings->set('tax_percent', 10, 'integer');
        $settings->set('service_percent', 5, 'integer');
        $settings->set('currency', 'IDR', 'string');
    }

    private function pricing(): PricingService
    {
        return app(PricingService::class);
    }

    public function test_base_pricing_on_weekdays_with_tax_and_service(): void
    {
        // Mon 3 Aug 2026 -> Wed 5 Aug (nights: Mon, Tue — both weekdays).
        $roomType = RoomType::factory()->create(['base_price' => 1_000_000, 'adult_capacity' => 2]);
        $quote = $this->pricing()->quote($roomType, new StayPeriod('2026-08-03', '2026-08-05'), rooms: 1, adults: 2);

        $this->assertSame(2_000_000, $quote->subtotalBeforeDiscount);
        $this->assertSame(0, $quote->discountTotal);
        $this->assertSame(200_000, $quote->taxTotal);      // 10% of 2,000,000
        $this->assertSame(100_000, $quote->serviceTotal);  // 5% of 2,000,000
        $this->assertSame(2_300_000, $quote->grandTotal);
    }

    public function test_weekend_surcharge_is_applied(): void
    {
        // Sat 8 Aug 2026 -> Sun 9 Aug: one weekend night.
        $roomType = RoomType::factory()->create(['base_price' => 1_000_000, 'adult_capacity' => 2]);
        $quote = $this->pricing()->quote($roomType, new StayPeriod('2026-08-08', '2026-08-09'), rooms: 1, adults: 2);

        // base 1,000,000 + 20% weekend = 1,200,000 subtotal
        $this->assertSame(1_200_000, $quote->subtotalBeforeDiscount);
    }

    public function test_seasonal_adjustment_is_applied(): void
    {
        $roomType = RoomType::factory()->create(['base_price' => 1_000_000, 'adult_capacity' => 2]);
        SeasonalRate::create([
            'room_type_id' => null,
            'name' => 'High',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-10',
            'adjustment_percent' => 25,
            'is_active' => true,
        ]);

        // Mon 3 -> Tue 4 (1 weekday night), +25% seasonal.
        $quote = $this->pricing()->quote($roomType, new StayPeriod('2026-08-03', '2026-08-04'), rooms: 1, adults: 2);

        $this->assertSame(1_250_000, $quote->subtotalBeforeDiscount);
    }

    public function test_extra_guest_fee_is_charged_per_night(): void
    {
        $roomType = RoomType::factory()->create(['base_price' => 1_000_000, 'adult_capacity' => 2, 'max_guests' => 4]);
        // 3 guests in 1 room, capacity 2 => 1 extra guest * 100,000 per night * 2 nights.
        $quote = $this->pricing()->quote($roomType, new StayPeriod('2026-08-03', '2026-08-05'), rooms: 1, adults: 3);

        $this->assertSame(2_200_000, $quote->subtotalBeforeDiscount); // 2,000,000 rooms + 200,000 extra guest
    }

    public function test_percentage_promotion_reduces_subtotal(): void
    {
        $roomType = RoomType::factory()->create(['base_price' => 1_000_000, 'adult_capacity' => 2]);
        Promotion::factory()->percentage(10)->create(['code' => 'SAVE10']);

        $quote = $this->pricing()->quote($roomType, new StayPeriod('2026-08-03', '2026-08-05'), rooms: 1, adults: 2, promoCode: 'SAVE10');

        $this->assertSame(200_000, $quote->discountTotal);        // 10% of 2,000,000
        $this->assertSame(1_800_000, $quote->subtotalAfterDiscount);
        $this->assertSame(180_000, $quote->taxTotal);             // tax on discounted subtotal
        $this->assertTrue($quote->hasPromotion());
    }

    public function test_fixed_promotion_reduces_subtotal(): void
    {
        $roomType = RoomType::factory()->create(['base_price' => 1_000_000, 'adult_capacity' => 2]);
        Promotion::factory()->fixed(300_000)->create(['code' => 'CUT300']);

        $quote = $this->pricing()->quote($roomType, new StayPeriod('2026-08-03', '2026-08-05'), rooms: 1, adults: 2, promoCode: 'CUT300');

        $this->assertSame(300_000, $quote->discountTotal);
        $this->assertSame(1_700_000, $quote->subtotalAfterDiscount);
    }

    public function test_invalid_promo_code_is_ignored_in_totals(): void
    {
        $roomType = RoomType::factory()->create(['base_price' => 1_000_000, 'adult_capacity' => 2]);

        $quote = $this->pricing()->quote($roomType, new StayPeriod('2026-08-03', '2026-08-05'), rooms: 1, adults: 2, promoCode: 'NOPE');

        $this->assertSame(0, $quote->discountTotal);
        $this->assertNull($quote->promotion);
    }

    public function test_night_snapshots_sum_exactly_to_grand_total(): void
    {
        $roomType = RoomType::factory()->create(['base_price' => 777_777, 'adult_capacity' => 2]);
        Promotion::factory()->percentage(13)->create(['code' => 'ODD13']);

        $quote = $this->pricing()->quote($roomType, new StayPeriod('2026-08-06', '2026-08-09'), rooms: 2, adults: 3, promoCode: 'ODD13');

        $sumFinal = array_sum(array_map(fn ($n) => $n->final, $quote->nights));
        $this->assertSame($quote->grandTotal, $sumFinal);

        // And discount + tax shares reconcile too.
        $this->assertSame($quote->discountTotal, array_sum(array_map(fn ($n) => $n->discount, $quote->nights)));
        $this->assertSame($quote->taxTotal + $quote->serviceTotal, array_sum(array_map(fn ($n) => $n->tax, $quote->nights)));
    }
}
