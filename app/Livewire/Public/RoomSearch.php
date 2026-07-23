<?php

namespace App\Livewire\Public;

use App\Services\Booking\AvailabilityService;
use App\Services\Pricing\PricingService;
use App\Services\Settings\SettingService;
use App\Support\StayPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class RoomSearch extends Component
{
    #[Url(as: 'checkin', keep: true)]
    public string $checkIn = '';

    #[Url(as: 'checkout', keep: true)]
    public string $checkOut = '';

    #[Url(keep: true)]
    public int $adults = 2;

    #[Url(keep: true)]
    public int $children = 0;

    #[Url(keep: true)]
    public int $rooms = 1;

    #[Url]
    public string $sort = 'price_asc';

    public bool $searched = false;

    public function mount(): void
    {
        if ($this->checkIn === '') {
            $this->checkIn = CarbonImmutable::tomorrow()->toDateString();
        }
        if ($this->checkOut === '') {
            $this->checkOut = CarbonImmutable::tomorrow()->addDay()->toDateString();
        }
        $this->searched = true;
    }

    protected function rules(): array
    {
        return [
            'checkIn' => ['required', 'date', 'after_or_equal:today'],
            'checkOut' => ['required', 'date', 'after:checkIn'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['required', 'integer', 'min:0', 'max:20'],
            'rooms' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    protected function messages(): array
    {
        return [
            'checkIn.after_or_equal' => 'Tanggal check-in tidak boleh sebelum hari ini.',
            'checkOut.after' => 'Tanggal check-out harus setelah tanggal check-in.',
            'rooms.min' => 'Jumlah kamar minimal 1.',
            'adults.min' => 'Minimal 1 tamu dewasa.',
        ];
    }

    public function search(): void
    {
        $this->validate();
        $this->searched = true;
    }

    public function render(): View
    {
        $results = collect();
        $error = null;
        $nights = 0;

        try {
            $validator = validator(
                ['checkIn' => $this->checkIn, 'checkOut' => $this->checkOut, 'adults' => $this->adults, 'children' => $this->children, 'rooms' => $this->rooms],
                $this->rules(),
                $this->messages(),
            );

            if ($validator->fails()) {
                $error = $validator->errors()->first();
            } else {
                $stay = new StayPeriod($this->checkIn, $this->checkOut);
                $nights = $stay->nights();
                $maxNights = app(SettingService::class)->integer('booking_max_nights', 30);

                if ($nights > $maxNights) {
                    $error = "Lama menginap maksimal {$maxNights} malam.";
                } else {
                    $pricing = app(PricingService::class);

                    $results = app(AvailabilityService::class)
                        ->search($stay, $this->adults, $this->children, $this->rooms)
                        ->map(function (array $row) use ($pricing, $stay) {
                            $quote = $pricing->quote(
                                $row['room_type'], $stay, $this->rooms, $this->adults, $this->children
                            );

                            return $row + ['quote' => $quote];
                        });

                    $results = match ($this->sort) {
                        'price_desc' => $results->sortByDesc(fn ($r) => $r['quote']->grandTotal)->values(),
                        'capacity' => $results->sortByDesc(fn ($r) => $r['room_type']->max_guests)->values(),
                        default => $results->sortBy(fn ($r) => $r['quote']->grandTotal)->values(),
                    };
                }
            }
        } catch (\InvalidArgumentException $e) {
            $error = 'Rentang tanggal tidak valid.';
        }

        return view('livewire.public.room-search', [
            'results' => $results,
            'searchError' => $error,
            'nights' => $nights,
        ])->layout('components.layouts.public', ['title' => 'Cari Kamar']);
    }
}
