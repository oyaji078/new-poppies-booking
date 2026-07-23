<?php

namespace App\Livewire\Admin;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Operations\CheckInService;
use App\Services\Operations\CheckOutService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use RuntimeException;

class FrontDesk extends Component
{
    public string $tab = 'arrivals'; // arrivals | inhouse | departures

    // Check-in modal state
    public ?int $checkInBookingId = null;

    /** @var array<int, array<int, int>> booking_item_id => [room_id, ...] */
    public array $selectedRooms = [];

    public string $earlyReason = '';

    public string $guestName = '';

    public string $idCardType = '';

    public string $idCardNumber = '';

    // Check-out modal state
    public ?int $checkOutBookingId = null;

    public int $extraCharges = 0;

    public string $checkOutNotes = '';

    // No-show
    public ?int $noShowBookingId = null;

    public string $noShowReason = '';

    public function openCheckIn(int $bookingId): void
    {
        $this->reset(['selectedRooms', 'earlyReason', 'guestName', 'idCardType', 'idCardNumber']);
        $this->checkInBookingId = $bookingId;
        $this->resetValidation();
    }

    public function closeModals(): void
    {
        $this->reset([
            'checkInBookingId', 'selectedRooms', 'earlyReason', 'guestName', 'idCardType', 'idCardNumber',
            'checkOutBookingId', 'extraCharges', 'checkOutNotes', 'noShowBookingId', 'noShowReason',
        ]);
        $this->resetValidation();
    }

    public function submitCheckIn(CheckInService $service): void
    {
        $booking = Booking::with('items')->findOrFail($this->checkInBookingId);

        $map = [];
        foreach ($booking->items as $item) {
            $map[$item->id] = array_values(array_filter($this->selectedRooms[$item->id] ?? []));
        }

        $guests = [];
        if (trim($this->guestName) !== '') {
            $guests[] = [
                'full_name' => $this->guestName,
                'id_card_type' => $this->idCardType ?: null,
                'id_card_number' => $this->idCardNumber ?: null,
            ];
        }

        try {
            $service->checkIn($booking, $map, auth()->user(), $guests, $this->earlyReason ?: null);
            session()->flash('success', "Check-in {$booking->code} berhasil.");
            $this->closeModals();
        } catch (RuntimeException $e) {
            $this->addError('selectedRooms', $e->getMessage());
        }
    }

    public function openCheckOut(int $bookingId): void
    {
        $this->reset(['extraCharges', 'checkOutNotes']);
        $this->checkOutBookingId = $bookingId;
        $this->resetValidation();
    }

    public function submitCheckOut(CheckOutService $service): void
    {
        $booking = Booking::with('items')->findOrFail($this->checkOutBookingId);

        try {
            $service->checkOut($booking, auth()->user(), max(0, $this->extraCharges), $this->checkOutNotes ?: null);
            session()->flash('success', "Check-out {$booking->code} berhasil.");
            $this->closeModals();
        } catch (RuntimeException $e) {
            $this->addError('checkOutNotes', $e->getMessage());
        }
    }

    public function openNoShow(int $bookingId): void
    {
        $this->reset(['noShowReason']);
        $this->noShowBookingId = $bookingId;
        $this->resetValidation();
    }

    public function submitNoShow(CheckOutService $service): void
    {
        $this->validate(['noShowReason' => ['required', 'string', 'min:5']], [
            'noShowReason.required' => 'Alasan wajib diisi.',
            'noShowReason.min' => 'Alasan minimal 5 karakter.',
        ]);

        $booking = Booking::findOrFail($this->noShowBookingId);

        try {
            $service->markNoShow($booking, auth()->user(), $this->noShowReason);
            session()->flash('success', "{$booking->code} ditandai tidak hadir.");
            $this->closeModals();
        } catch (RuntimeException $e) {
            $this->addError('noShowReason', $e->getMessage());
        }
    }

    public function render(): View
    {
        $today = today()->toDateString();

        $bookings = match ($this->tab) {
            'inhouse' => Booking::query()
                ->where('status', BookingStatus::CHECKED_IN->value)
                ->with('items.assignments.room')
                ->orderBy('check_out_date')
                ->get(),
            'departures' => Booking::query()
                ->where('status', BookingStatus::CHECKED_IN->value)
                ->whereDate('check_out_date', '<=', $today)
                ->with('items.assignments.room')
                ->get(),
            default => Booking::query()
                ->where('status', BookingStatus::CONFIRMED->value)
                ->whereDate('check_in_date', '<=', $today)
                ->with('items')
                ->orderBy('check_in_date')
                ->get(),
        };

        $checkInBooking = $this->checkInBookingId
            ? Booking::with('items.roomType')->find($this->checkInBookingId)
            : null;

        $availableRooms = [];
        if ($checkInBooking) {
            $service = app(CheckInService::class);
            foreach ($checkInBooking->items as $item) {
                $availableRooms[$item->id] = $service->availableRoomsFor($checkInBooking, $item->room_type_id);
            }
        }

        return view('livewire.admin.front-desk', [
            'bookings' => $bookings,
            'checkInBooking' => $checkInBooking,
            'availableRooms' => $availableRooms,
            'checkOutBooking' => $this->checkOutBookingId ? Booking::find($this->checkOutBookingId) : null,
            'noShowBooking' => $this->noShowBookingId ? Booking::find($this->noShowBookingId) : null,
        ])->layout('components.layouts.admin', [
            'title' => 'Check-in / Check-out',
            'heading' => 'Front Desk',
            'breadcrumb' => 'Admin / Check-in & Check-out',
        ]);
    }
}
