<?php

namespace Tests\Feature\Pricing;

use App\Models\Promotion;
use App\Models\RoomType;
use App\Services\Pricing\PromotionService;
use App\Support\StayPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PromotionService
    {
        return app(PromotionService::class);
    }

    public function test_expired_promotion_is_rejected(): void
    {
        $roomType = RoomType::factory()->create();
        Promotion::factory()->expired()->create(['code' => 'DEAD']);

        $result = $this->service()->evaluateCode('DEAD', $roomType, new StayPeriod('2026-08-10', '2026-08-12'), 1_000_000);

        $this->assertFalse($result->applied());
        $this->assertNotNull($result->error);
    }

    public function test_exhausted_quota_is_rejected(): void
    {
        $roomType = RoomType::factory()->create();
        Promotion::factory()->percentage(10)->create(['code' => 'FULL', 'usage_limit' => 5, 'used_count' => 5]);

        $result = $this->service()->evaluateCode('FULL', $roomType, new StayPeriod('2026-08-10', '2026-08-12'), 1_000_000);

        $this->assertFalse($result->applied());
        $this->assertStringContainsString('Kuota', $result->error);
    }

    public function test_min_nights_is_enforced(): void
    {
        $roomType = RoomType::factory()->create();
        Promotion::factory()->percentage(10)->create(['code' => 'STAY3', 'min_nights' => 3]);

        $result = $this->service()->evaluateCode('STAY3', $roomType, new StayPeriod('2026-08-10', '2026-08-12'), 1_000_000);

        $this->assertFalse($result->applied());
    }

    public function test_room_type_restriction_is_enforced(): void
    {
        $allowed = RoomType::factory()->create();
        $other = RoomType::factory()->create();
        $promo = Promotion::factory()->percentage(10)->create(['code' => 'DLXONLY']);
        $promo->roomTypes()->attach($allowed->id);

        $rejected = $this->service()->evaluateCode('DLXONLY', $other, new StayPeriod('2026-08-10', '2026-08-12'), 1_000_000);
        $accepted = $this->service()->evaluateCode('DLXONLY', $allowed, new StayPeriod('2026-08-10', '2026-08-12'), 1_000_000);

        $this->assertFalse($rejected->applied());
        $this->assertTrue($accepted->applied());
    }

    public function test_percentage_discount_is_capped_by_max_discount(): void
    {
        $roomType = RoomType::factory()->create();
        Promotion::factory()->percentage(50, 100_000)->create(['code' => 'CAP']);

        $result = $this->service()->evaluateCode('CAP', $roomType, new StayPeriod('2026-08-10', '2026-08-12'), 1_000_000);

        // 50% of 1,000,000 = 500,000 but capped at 100,000.
        $this->assertSame(100_000, $result->discount);
    }

    public function test_fixed_discount_never_exceeds_subtotal(): void
    {
        $roomType = RoomType::factory()->create();
        Promotion::factory()->fixed(2_000_000)->create(['code' => 'BIG']);

        $result = $this->service()->evaluateCode('BIG', $roomType, new StayPeriod('2026-08-10', '2026-08-12'), 500_000);

        $this->assertSame(500_000, $result->discount);
    }
}
