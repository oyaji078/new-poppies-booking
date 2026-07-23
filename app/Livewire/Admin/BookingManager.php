<?php

namespace App\Livewire\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BookingManager extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $paymentStatus = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public ?int $detailId = null;

    public function updating($field): void
    {
        if (in_array($field, ['search', 'status', 'paymentStatus', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'paymentStatus', 'from', 'to']);
        $this->resetPage();
    }

    public function showDetail(int $id): void
    {
        $this->detailId = $id;
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
    }

    public function render(): View
    {
        $bookings = Booking::query()
            ->with(['items'])
            ->when($this->search, function ($q) {
                $term = "%{$this->search}%";
                $q->where(fn ($sub) => $sub->where('code', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term));
            })
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->paymentStatus, fn ($q) => $q->where('payment_status', $this->paymentStatus))
            ->when($this->from, fn ($q) => $q->whereDate('check_in_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('check_in_date', '<=', $this->to))
            ->latest('id')
            ->paginate(15);

        $detail = $this->detailId
            ? Booking::with(['items.nights', 'guests', 'paymentAttempts'])->find($this->detailId)
            : null;

        return view('livewire.admin.booking-manager', [
            'bookings' => $bookings,
            'detail' => $detail,
            'statuses' => BookingStatus::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
        ])->layout('components.layouts.admin', [
            'title' => 'Reservasi',
            'heading' => 'Reservasi',
            'breadcrumb' => 'Admin / Reservasi',
        ]);
    }
}
