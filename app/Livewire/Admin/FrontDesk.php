<?php

namespace App\Livewire\Admin;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Services\Operations\CheckInService;
use App\Services\Operations\CheckOutService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

class FrontDesk extends Component
{
    /** Status filter for the board; empty string shows everything. */
    #[Url(as: 'status', keep: true)]
    public string $filter = '';

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

    /**
     * Card colour per stage of the stay. A booking starts white ("belum
     * check-in"), turns green the moment check-in is confirmed and blue after
     * check-out, so the desk reads the day's state at a glance without tabs.
     *
     * Full Tailwind class strings (never interpolated fragments) — app/ is in
     * the CSS @source globs, so the JIT compiler finds them here.
     *
     * @return array{card: string, label: string, badge: string}
     */
    public static function tone(BookingStatus $status): array
    {
        return match ($status) {
            BookingStatus::CHECKED_IN => [
                'card' => 'border-emerald-300 bg-emerald-50',
                'label' => 'Sudah check-in',
                'badge' => 'bg-emerald-100 text-emerald-700',
            ],
            BookingStatus::CHECKED_OUT => [
                'card' => 'border-sky-300 bg-sky-50',
                'label' => 'Sudah check-out',
                'badge' => 'bg-sky-100 text-sky-700',
            ],
            BookingStatus::NO_SHOW => [
                'card' => 'border-rose-300 bg-rose-50',
                'label' => 'Tidak hadir',
                'badge' => 'bg-rose-100 text-rose-700',
            ],
            default => [
                'card' => 'border-slate-200 bg-white',
                'label' => 'Belum check-in',
                'badge' => 'bg-slate-100 text-slate-600',
            ],
        };
    }

    public function openCheckIn(int $bookingId): void
    {
        $this->reset(['selectedRooms', 'earlyReason', 'guestName', 'idCardType', 'idCardNumber']);
        $this->checkInBookingId = $bookingId;

        // Seed one empty array per booking item BEFORE the checkboxes render.
        // A group of checkboxes sharing a wire:model only accumulates into an
        // array if the property already holds one; against an unset property
        // Livewire binds a single boolean, so every box ticks at once and the
        // submit blows up on array_filter(). This line is the whole fix.
        $this->selectedRooms = Booking::with('items')
            ->findOrFail($bookingId)
            ->items
            ->mapWithKeys(fn (BookingItem $item) => [$item->id => []])
            ->all();

        $this->resetValidation();
    }

    /**
     * The four stages the board can be narrowed to, in board order.
     *
     * @return array<int, BookingStatus>
     */
    public static function filterableStatuses(): array
    {
        return [
            BookingStatus::CONFIRMED,
            BookingStatus::CHECKED_IN,
            BookingStatus::CHECKED_OUT,
            BookingStatus::NO_SHOW,
        ];
    }

    /**
     * Clicking the active filter again clears it — the chips double as the
     * colour legend, so there must always be a way back to "everything".
     */
    public function setFilter(string $status): void
    {
        $this->filter = $this->filter === $status ? '' : $status;
    }

    /**
     * Fill the picker with the first free rooms for every item.
     *
     * A convenience only — the same rules are enforced again in CheckInService
     * under a row lock, because the admin can still edit the selection after
     * this and two desks can be checking in at the same moment.
     */
    public function autoAssignRooms(CheckInService $service): void
    {
        $booking = Booking::with('items')->findOrFail($this->checkInBookingId);

        // Two items can share a room type, so a room handed to the first must
        // not be offered to the second.
        $taken = [];

        foreach ($booking->items as $item) {
            $picked = $service->availableRoomsFor($booking, $item->room_type_id)
                ->reject(fn ($room) => in_array((string) $room->id, $taken, true))
                ->take($item->rooms)
                ->map(fn ($room) => (string) $room->id)
                ->values()
                ->all();

            $taken = array_merge($taken, $picked);
            $this->selectedRooms[$item->id] = $picked;
        }
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
            // Cast defensively: the browser is not the only thing that can put a
            // value here, and a non-array must fail as a validation message
            // rather than a 500.
            $selection = $this->selectedRooms[$item->id] ?? [];
            $map[$item->id] = is_array($selection)
                ? array_values(array_filter($selection))
                : [];
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

        // One list instead of arrival/in-house/departure tabs. A booking stays
        // on the board through its whole stay and only leaves the day after
        // check-out, so the colour change after each action is visible.
        $bookings = Booking::query()
            ->where(function ($query) use ($today) {
                $query
                    // Due to arrive: today's arrivals plus any left unattended.
                    ->where(fn ($q) => $q
                        ->where('status', BookingStatus::CONFIRMED->value)
                        ->whereDate('check_in_date', '<=', $today))
                    // In-house: always shown until checked out.
                    ->orWhere('status', BookingStatus::CHECKED_IN->value)
                    // Settled today — kept so the action's result stays on screen.
                    ->orWhere(fn ($q) => $q
                        ->where('status', BookingStatus::CHECKED_OUT->value)
                        ->whereDate('checked_out_at', $today))
                    ->orWhere(fn ($q) => $q
                        ->where('status', BookingStatus::NO_SHOW->value)
                        ->whereDate('check_in_date', $today));
            })
            ->with('items.assignments.room')
            ->orderBy('check_in_date')
            ->get()
            // Bookings still needing action float to the top; settled ones sink.
            ->sortBy(fn (Booking $booking) => match ($booking->status) {
                BookingStatus::CONFIRMED => 0,
                BookingStatus::CHECKED_IN => 1,
                BookingStatus::CHECKED_OUT => 2,
                default => 3,
            })
            ->values();

        // Counts come from the whole board, not the filtered view, so the chips
        // keep telling the truth about what is hidden behind them.
        $counts = $bookings->countBy(fn (Booking $booking) => $booking->status->value);

        if ($this->filter !== '') {
            $bookings = $bookings
                ->filter(fn (Booking $booking) => $booking->status->value === $this->filter)
                ->values();
        }

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
            'counts' => $counts,
            'totalCount' => $counts->sum(),
        ])->layout('components.layouts.admin', [
            'title' => 'Check-in / Check-out',
            'heading' => 'Front Desk',
            'breadcrumb' => 'Admin / Check-in & Check-out',
        ]);
    }
}
