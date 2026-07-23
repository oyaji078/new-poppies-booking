<?php

namespace App\Livewire\Admin;

use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\Refund;
use App\Services\Operations\RefundService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use RuntimeException;

class CancellationRefundManager extends Component
{
    public string $tab = 'cancellations'; // cancellations | refunds

    // New refund form
    public ?int $refundBookingId = null;

    public ?int $refundAmount = null;

    public string $refundReason = '';

    // Status update
    public ?int $updatingRefundId = null;

    public string $newStatus = '';

    public string $statusNotes = '';

    public string $providerReference = '';

    public function openRefundForm(int $bookingId, RefundService $service): void
    {
        $booking = Booking::findOrFail($bookingId);
        $this->refundBookingId = $bookingId;
        $this->refundAmount = $service->refundableRemaining($booking);
        $this->refundReason = '';
        $this->resetValidation();
    }

    public function closeForms(): void
    {
        $this->reset(['refundBookingId', 'refundAmount', 'refundReason', 'updatingRefundId', 'newStatus', 'statusNotes', 'providerReference']);
        $this->resetValidation();
    }

    public function submitRefund(RefundService $service): void
    {
        $this->validate([
            'refundAmount' => ['required', 'integer', 'min:1'],
            'refundReason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'refundAmount.required' => 'Jumlah refund wajib diisi.',
            'refundReason.required' => 'Alasan refund wajib diisi.',
        ]);

        $booking = Booking::findOrFail($this->refundBookingId);

        try {
            $service->request($booking, (int) $this->refundAmount, $this->refundReason, auth()->user());
            session()->flash('success', 'Permintaan refund dicatat. Tandai selesai setelah dana benar-benar dikirim.');
            $this->closeForms();
            $this->tab = 'refunds';
        } catch (RuntimeException $e) {
            $this->addError('refundAmount', $e->getMessage());
        }
    }

    public function openStatusForm(int $refundId): void
    {
        $this->updatingRefundId = $refundId;
        $this->newStatus = '';
        $this->statusNotes = '';
        $this->providerReference = '';
        $this->resetValidation();
    }

    public function submitStatus(RefundService $service): void
    {
        $this->validate([
            'newStatus' => ['required', 'string'],
            'statusNotes' => ['nullable', 'string', 'max:500'],
        ], ['newStatus.required' => 'Pilih status baru.']);

        $refund = Refund::findOrFail($this->updatingRefundId);

        try {
            $service->updateStatus(
                $refund,
                RefundStatus::from($this->newStatus),
                auth()->user(),
                $this->statusNotes ?: null,
                $this->providerReference ?: null,
            );
            session()->flash('success', 'Status refund diperbarui.');
            $this->closeForms();
        } catch (RuntimeException $e) {
            $this->addError('newStatus', $e->getMessage());
        }
    }

    public function render(): View
    {
        $cancellations = CancellationRequest::query()
            ->with('booking')
            ->latest('id')
            ->limit(100)
            ->get();

        $refunds = Refund::query()
            ->with(['booking', 'processedBy'])
            ->latest('id')
            ->limit(100)
            ->get();

        return view('livewire.admin.cancellation-refund-manager', [
            'cancellations' => $cancellations,
            'refunds' => $refunds,
            'refundStatuses' => RefundStatus::cases(),
            'refundBooking' => $this->refundBookingId ? Booking::find($this->refundBookingId) : null,
        ])->layout('components.layouts.admin', [
            'title' => 'Pembatalan & Refund',
            'heading' => 'Pembatalan & Refund',
            'breadcrumb' => 'Admin / Pembatalan & Refund',
        ]);
    }
}
