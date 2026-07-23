<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\Operations\CancellationService;
use App\Services\Settings\SettingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class BookingController extends Controller
{
    public function lookupForm(): View
    {
        return view('public.booking.lookup');
    }

    /**
     * Guest access requires BOTH the booking code and the customer email (§5).
     * A booking code alone must never reveal booking details.
     */
    public function lookup(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255'],
        ], [
            'code.required' => 'Kode pemesanan wajib diisi.',
            'email.required' => 'Email wajib diisi.',
        ]);

        $booking = Booking::query()
            ->where('code', trim($data['code']))
            ->whereRaw('LOWER(customer_email) = ?', [mb_strtolower(trim($data['email']))])
            ->first();

        if (! $booking) {
            // Deliberately generic so the form can't be used to probe which
            // booking codes exist.
            throw ValidationException::withMessages([
                'code' => 'Kode pemesanan dan email tidak cocok.',
            ]);
        }

        $request->session()->put('booking_access.'.$booking->code, $booking->customer_email);

        return redirect()->route('booking.show', $booking->code);
    }

    public function show(Request $request, Booking $booking): View
    {
        $this->authorizeBookingAccess($request, $booking);

        $booking->load(['items.nights', 'items.roomType', 'guests', 'paymentAttempts']);

        return view('public.booking.show', compact('booking'));
    }

    /**
     * A branded, single-page invoice, guarded by the same access rule as the
     * booking. Reads hotel identity from settings so it stays correct if the
     * business details change.
     */
    public function invoice(Request $request, Booking $booking, SettingService $settings): View
    {
        $this->authorizeBookingAccess($request, $booking);

        $booking->load(['items.nights', 'items.roomType', 'guests']);

        $hotel = [
            'name' => (string) $settings->get('hotel_name', config('app.name')),
            'address' => (string) $settings->get('hotel_address', ''),
            'phone' => (string) $settings->get('hotel_phone', ''),
            'email' => (string) $settings->get('hotel_email', ''),
        ];

        return view('public.booking.invoice', compact('booking', 'hotel'));
    }

    /**
     * Customer-initiated cancellation. Inventory is released and refund
     * eligibility recorded; the refund itself is handled by an admin (§22).
     */
    public function cancel(Request $request, Booking $booking, CancellationService $service)
    {
        $this->authorizeBookingAccess($request, $booking);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'reason.min' => 'Alasan pembatalan minimal 5 karakter.',
        ]);

        try {
            $cancellation = $service->cancel($booking, $data['reason'], $request->user(), isAdmin: false);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = 'Pemesanan Anda telah dibatalkan.';
        if ($cancellation->refund_estimate > 0) {
            $message .= ' Pengembalian dana sebesar '.rupiah($cancellation->refund_estimate)
                .' akan diproses oleh tim kami.';
        } elseif ($booking->fresh()->payment_status->value === 'refund_pending') {
            $message .= ' Sesuai kebijakan pembatalan, tidak ada pengembalian dana untuk pembatalan ini.';
        }

        return redirect()->route('booking.show', $booking->code)->with('status', $message);
    }

    /**
     * Allow when the visitor either owns the booking (logged in), is staff, or
     * has proven the email through the lookup form in this session.
     */
    private function authorizeBookingAccess(Request $request, Booking $booking): void
    {
        $user = $request->user();

        if ($user && ($user->isStaff() || $booking->user_id === $user->id)) {
            return;
        }

        $granted = $request->session()->get('booking_access.'.$booking->code);

        abort_unless($granted && $granted === $booking->customer_email, 403, 'Silakan verifikasi kode pemesanan dan email Anda.');
    }
}
