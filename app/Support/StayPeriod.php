<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A stay period. The checkout date is NOT a stay night: check-in 10 Aug,
 * check-out 13 Aug => stay nights are 10, 11, 12 (3 nights).
 */
final class StayPeriod
{
    public readonly CarbonImmutable $checkIn;

    public readonly CarbonImmutable $checkOut;

    public function __construct(string|CarbonImmutable $checkIn, string|CarbonImmutable $checkOut)
    {
        $this->checkIn = CarbonImmutable::parse($checkIn)->startOfDay();
        $this->checkOut = CarbonImmutable::parse($checkOut)->startOfDay();

        if ($this->checkOut <= $this->checkIn) {
            throw new InvalidArgumentException('Check-out date must be after check-in date.');
        }
    }

    public function nights(): int
    {
        return (int) $this->checkIn->diffInDays($this->checkOut);
    }

    /**
     * Every night that is actually occupied (excludes the checkout date).
     *
     * @return array<int, CarbonImmutable>
     */
    public function stayDates(): array
    {
        $dates = [];
        for ($date = $this->checkIn; $date < $this->checkOut; $date = $date->addDay()) {
            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * @return array<int, string> stay dates as Y-m-d strings
     */
    public function stayDateStrings(): array
    {
        return array_map(fn (CarbonImmutable $d) => $d->toDateString(), $this->stayDates());
    }
}
