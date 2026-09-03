<?php

namespace App\Livewire\Public;

use App\Models\RoomType;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingExpirationService;
use App\Services\Settings\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Month calendar of real availability for one room type, used to pick the stay
 * dates before checkout.
 *
 * The distinction that drives everything here: a calendar cell is a NIGHT. A
 * fully booked date can still be a valid check-out date, because the checkout
 * day is never occupied — so "full" blocks starting or passing through a date,
 * never ending on it.
 */
class AvailabilityCalendar extends Component
{
    public RoomType $roomType;

    /** First day of the displayed month, Y-m-d. */
    public string $month = '';

    public string $checkIn = '';

    public string $checkOut = '';

    public int $rooms = 1;

    public int $adults = 2;

    public int $children = 0;

    public function mount(RoomType $roomType): void
    {
        $this->roomType = $roomType;
        $this->month = CarbonImmutable::today()->startOfMonth()->toDateString();

        // Holds that already lapsed must not read as "booked" on a public
        // calendar. Vercel has no scheduler, so sweep on real traffic.
        app(BookingExpirationService::class)->expireDueHolds();
    }

    public function previousMonth(): void
    {
        $candidate = CarbonImmutable::parse($this->month)->subMonth();

        // Never page back into months that are entirely in the past.
        if ($candidate->endOfMonth()->lt(CarbonImmutable::today())) {
            return;
        }

        $this->month = $candidate->toDateString();
    }

    public function nextMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month)->addMonth()->toDateString();
    }

    public function clearSelection(): void
    {
        $this->reset(['checkIn', 'checkOut']);
    }

    public function selectDate(string $date): void
    {
        $availability = $this->availability();

        if (! isset($availability[$date]) || CarbonImmutable::parse($date)->lt(CarbonImmutable::today())) {
            return;
        }

        $startingOver = $this->checkIn === '' || $this->checkOut !== '' || $date <= $this->checkIn;

        if ($startingOver) {
            // The first night of the stay is occupied, so it must be free.
            if ($availability[$date] < $this->rooms) {
                return;
            }

            $this->checkIn = $date;
            $this->checkOut = '';

            return;
        }

        // Completing the range. Guarded by the same rule the cells are disabled
        // with, so a crafted request cannot select an unavailable stay either.
        if ($this->latestCheckOut() !== null && $date > $this->latestCheckOut()) {
            return;
        }

        $this->checkOut = $date;
    }

    /**
     * Availability for every night shown on the grid, plus a tail so a stay can
     * run past the end of the visible month.
     *
     * @return array<string, int>
     */
    public function availability(): array
    {
        $start = $this->gridStart();

        return app(AvailabilityService::class)->dailyAvailability(
            $this->roomType,
            $start->toDateString(),
            // 6 grid weeks + room for a stay continuing into the next month.
            $start->addDays(41 + $this->maxNights())->toDateString(),
        );
    }

    /**
     * The last date the guest may check out on, once check-in is chosen.
     *
     * Bounded by whichever comes first: the first fully booked night after
     * check-in (you cannot sleep through it) or the max-nights policy.
     */
    public function latestCheckOut(): ?string
    {
        if ($this->checkIn === '') {
            return null;
        }

        $availability = $this->availability();
        $checkIn = CarbonImmutable::parse($this->checkIn);
        $limit = $checkIn->addDays($this->maxNights());

        for ($date = $checkIn->addDay(); $date <= $limit; $date = $date->addDay()) {
            $key = $date->toDateString();

            // Checking out ON this date is still fine — the night before it was
            // the last one occupied. Sleeping THROUGH it is not.
            if (! isset($availability[$key]) || $availability[$key] < $this->rooms) {
                return $key;
            }
        }

        return $limit->toDateString();
    }

    public function maxNights(): int
    {
        return max(1, app(SettingService::class)->integer('booking_max_nights', 30));
    }

    public function nights(): int
    {
        if ($this->checkIn === '' || $this->checkOut === '') {
            return 0;
        }

        return (int) CarbonImmutable::parse($this->checkIn)->diffInDays(CarbonImmutable::parse($this->checkOut));
    }

    /**
     * Monday of the week the displayed month begins in.
     */
    private function gridStart(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->month)->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY);
    }

    public function render(): View
    {
        $availability = $this->availability();
        $today = CarbonImmutable::today();
        $monthStart = CarbonImmutable::parse($this->month)->startOfMonth();
        $latestCheckOut = $this->latestCheckOut();

        $days = [];
        for ($i = 0; $i < 42; $i++) {
            $date = $this->gridStart()->addDays($i);
            $key = $date->toDateString();
            $free = $availability[$key] ?? 0;

            $isPast = $date->lt($today);
            $selectingEnd = $this->checkIn !== '' && $this->checkOut === '';

            // While picking the end date, a full night is still a legal checkout
            // day — that is why "selectable" differs between the two phases.
            $selectable = ! $isPast && (
                $selectingEnd
                    ? ($key > $this->checkIn && $latestCheckOut !== null && $key <= $latestCheckOut)
                    : $free >= $this->rooms
            );

            $inRange = $this->checkIn !== '' && $this->checkOut !== ''
                && $key > $this->checkIn && $key < $this->checkOut;

            $days[] = [
                'date' => $key,
                'day' => $date->day,
                'in_month' => $date->isSameMonth($monthStart),
                'is_today' => $date->isSameDay($today),
                'is_past' => $isPast,
                'free' => $free,
                'is_full' => $free < $this->rooms,
                'selectable' => $selectable,
                'is_check_in' => $key === $this->checkIn,
                'is_check_out' => $key === $this->checkOut,
                'in_range' => $inRange,
            ];
        }

        return view('livewire.public.availability-calendar', [
            'days' => $days,
            'monthLabel' => $monthStart->translatedFormat('F Y'),
            'canPageBack' => $monthStart->gt($today->startOfMonth()),
            'nights' => $this->nights(),
        ]);
    }
}
