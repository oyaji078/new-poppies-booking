<?php

namespace App\Livewire\Public;

use App\Exceptions\BookingException;
use App\Models\RoomType;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingService;
use App\Services\Pricing\PricingService;
use App\Services\Pricing\PromotionService;
use App\Support\Pricing\PriceQuote;
use App\Support\StayPeriod;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CheckoutWizard extends Component
{
    public RoomType $roomType;

    public string $checkIn;

    public string $checkOut;

    public int $adults = 2;

    public int $children = 0;

    public int $rooms = 1;

    /** 1 = guest details, 2 = review */
    public int $step = 1;

    // Guest details
    public string $customer_name = '';

    public string $customer_email = '';

    public string $customer_phone = '';

    public string $customer_country = 'Indonesia';

    /** @var array<int, string> */
    public array $guest_names = [];

    public string $arrival_time = '';

    public string $special_request = '';

    public bool $terms = false;

    // Promotion
    public string $promo_code = '';

    public ?string $promoMessage = null;

    public bool $promoApplied = false;

    public ?string $bookingError = null;

    public function mount(RoomType $roomType): void
    {
        abort_unless($roomType->is_published, 404);

        $this->roomType = $roomType;
        $this->checkIn = request()->query('checkin', now()->addDay()->toDateString());
        $this->checkOut = request()->query('checkout', now()->addDays(2)->toDateString());
        $this->adults = max(1, (int) request()->query('adults', 2));
        $this->children = max(0, (int) request()->query('children', 0));
        $this->rooms = max(1, (int) request()->query('rooms', 1));

        if ($user = auth()->user()) {
            $this->customer_name = $user->name;
            $this->customer_email = $user->email;
            $this->customer_phone = (string) $user->phone;
        }

        $this->guest_names = [''];
    }

    protected function detailRules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'customer_country' => ['nullable', 'string', 'max:80'],
            'guest_names' => ['array'],
            'guest_names.*' => ['nullable', 'string', 'max:255'],
            'arrival_time' => ['nullable', 'string', 'max:20'],
            'special_request' => ['nullable', 'string', 'max:1000'],
            'terms' => ['accepted'],
        ];
    }

    protected function messages(): array
    {
        return [
            'customer_name.required' => 'Nama lengkap wajib diisi.',
            'customer_email.required' => 'Email wajib diisi.',
            'customer_email.email' => 'Format email tidak valid.',
            'customer_phone.required' => 'Nomor telepon wajib diisi.',
            'terms.accepted' => 'Anda harus menyetujui syarat dan ketentuan.',
        ];
    }

    public function goToReview(): void
    {
        $this->validate($this->detailRules(), $this->messages());
        $this->step = 2;
    }

    public function backToDetails(): void
    {
        $this->step = 1;
    }

    public function applyPromo(): void
    {
        $this->promoMessage = null;
        $this->promoApplied = false;

        if (trim($this->promo_code) === '') {
            $this->promoMessage = 'Masukkan kode promo terlebih dahulu.';

            return;
        }

        $quote = $this->quote();

        if ($quote->hasPromotion()) {
            $this->promoApplied = true;
            $this->promoMessage = 'Kode promo berhasil diterapkan.';
        } else {
            // Re-run just the promo evaluation to surface the precise reason.
            $result = app(PromotionService::class)->evaluateCode(
                trim($this->promo_code),
                $this->roomType,
                $this->stay(),
                $this->quoteWithoutPromo()->subtotalBeforeDiscount,
            );
            $this->promoMessage = $result->error ?? 'Kode promo tidak dapat digunakan.';
        }
    }

    public function clearPromo(): void
    {
        $this->promo_code = '';
        $this->promoApplied = false;
        $this->promoMessage = null;
    }

    private function stay(): StayPeriod
    {
        return new StayPeriod($this->checkIn, $this->checkOut);
    }

    private function quote(): PriceQuote
    {
        return app(PricingService::class)->quote(
            $this->roomType, $this->stay(), $this->rooms, $this->adults, $this->children,
            trim($this->promo_code) !== '' ? trim($this->promo_code) : null,
        );
    }

    private function quoteWithoutPromo(): PriceQuote
    {
        return app(PricingService::class)->quote(
            $this->roomType, $this->stay(), $this->rooms, $this->adults, $this->children
        );
    }

    /**
     * Create the hold. Price and availability are recomputed server-side here —
     * nothing from the browser is trusted.
     */
    public function confirmBooking(BookingService $bookings)
    {
        $this->validate($this->detailRules(), $this->messages());
        $this->bookingError = null;

        try {
            $booking = $bookings->createHold(
                $this->roomType,
                $this->stay(),
                $this->rooms,
                $this->adults,
                $this->children,
                [
                    'customer_name' => $this->customer_name,
                    'customer_email' => $this->customer_email,
                    'customer_phone' => $this->customer_phone,
                    'customer_country' => $this->customer_country,
                    'special_request' => $this->special_request ?: null,
                    'arrival_time' => $this->arrival_time ?: null,
                    'guest_names' => $this->guest_names,
                    'promo_code' => trim($this->promo_code) !== '' ? trim($this->promo_code) : null,
                    'user_id' => auth()->id(),
                ],
            );

            // Remember this booking so the guest can view it without re-entering the email.
            session()->put('booking_access.'.$booking->code, $booking->customer_email);

            return redirect()->route('booking.show', $booking->code);
        } catch (BookingException $e) {
            $this->bookingError = $e->getMessage();
        }
    }

    public function render(): View
    {
        $quote = null;
        $available = 0;
        $error = null;

        try {
            $stay = $this->stay();
            $quote = $this->quote();
            $available = app(AvailabilityService::class)->availableUnits($this->roomType, $stay);
        } catch (\InvalidArgumentException $e) {
            $error = 'Rentang tanggal tidak valid.';
        }

        return view('livewire.public.checkout-wizard', [
            'quote' => $quote,
            'available' => $available,
            'dateError' => $error,
        ])->layout('components.layouts.public', ['title' => 'Pemesanan']);
    }
}
