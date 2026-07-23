<?php

namespace Tests\Unit\Support;

use App\Support\StayPeriod;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class StayPeriodTest extends TestCase
{
    public function test_it_counts_nights_excluding_checkout(): void
    {
        // 10 Aug -> 13 Aug = 3 nights (10, 11, 12).
        $stay = new StayPeriod('2026-08-10', '2026-08-13');

        $this->assertSame(3, $stay->nights());
        $this->assertSame(['2026-08-10', '2026-08-11', '2026-08-12'], $stay->stayDateStrings());
    }

    public function test_single_night_stay(): void
    {
        $stay = new StayPeriod('2026-08-10', '2026-08-11');

        $this->assertSame(1, $stay->nights());
        $this->assertSame(['2026-08-10'], $stay->stayDateStrings());
    }

    public function test_checkout_must_be_after_checkin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StayPeriod('2026-08-13', '2026-08-10');
    }

    public function test_equal_dates_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StayPeriod('2026-08-10', '2026-08-10');
    }
}
