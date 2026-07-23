<?php

namespace App\Livewire\Admin;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Payments\PaymentReviewService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use RuntimeException;

class PaymentReviewQueue extends Component
{
    public ?int $actingId = null;

    public string $action = '';   // approve | reject

    public string $reason = '';

    public function startAction(int $bookingId, string $action): void
    {
        $this->actingId = $bookingId;
        $this->action = $action;
        $this->reason = '';
        $this->resetValidation();
    }

    public function cancelAction(): void
    {
        $this->reset(['actingId', 'action', 'reason']);
    }

    public function submit(PaymentReviewService $service): void
    {
        $this->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan wajib diisi dan akan dicatat di audit log.',
            'reason.min' => 'Alasan minimal 5 karakter.',
        ]);

        $booking = Booking::findOrFail($this->actingId);

        try {
            if ($this->action === 'approve') {
                $service->approve($booking, $this->reason);
                session()->flash('success', 'Pemesanan '.$booking->code.' dikonfirmasi.');
            } else {
                $service->reject($booking, $this->reason);
                session()->flash('success', 'Pemesanan '.$booking->code.' ditolak dan dibatalkan.');
            }

            $this->cancelAction();
        } catch (RuntimeException $e) {
            $this->addError('reason', $e->getMessage());
        }
    }

    public function render(): View
    {
        $bookings = Booking::query()
            ->where('status', BookingStatus::PAYMENT_REVIEW->value)
            ->with(['items', 'paymentAttempts'])
            ->latest('updated_at')
            ->get();

        return view('livewire.admin.payment-review-queue', compact('bookings'))
            ->layout('components.layouts.admin', [
                'title' => 'Peninjauan Pembayaran',
                'heading' => 'Peninjauan Pembayaran',
                'breadcrumb' => 'Admin / Peninjauan Pembayaran',
            ]);
    }
}
