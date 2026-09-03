<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Services\Doku\DokuCheckoutService;
use App\Services\Doku\PaymentCallbackToken;
use App\Services\Payments\CashPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PaymentController extends Controller
{
    /**
     * Create (or reuse) a DOKU payment page and send the customer there.
     */
    public function start(Request $request, Booking $booking, DokuCheckoutService $checkout)
    {
        $this->authorizeBookingAccess($request, $booking);

        try {
            $attempt = $checkout->startPayment($booking);
        } catch (BookingException $e) {
            return redirect()->route('booking.show', $booking->code)->with('error', $e->getMessage());
        } catch (RuntimeException $e) {
            report($e);

            return redirect()->route('booking.show', $booking->code)
                ->with('error', 'Pembayaran belum dapat diproses saat ini. Silakan coba lagi beberapa saat.');
        }

        return redirect()->away($attempt->payment_url);
    }

    /**
     * "Bayar di tempat": reserve the room now, hand over cash at the front desk.
     *
     * POST only, and behind the same access check as the gateway flow — this
     * takes a room off sale, so a link scanner must never be able to fire it.
     */
    public function cash(Request $request, Booking $booking, CashPaymentService $cash): RedirectResponse
    {
        $this->authorizeBookingAccess($request, $booking);

        try {
            $cash->reserve($booking);
        } catch (RuntimeException $e) {
            return redirect()->route('booking.show', $booking->code)->with('error', $e->getMessage());
        }

        return redirect()->route('booking.show', $booking->code)->with(
            'success',
            'Pemesanan dikonfirmasi. Silakan lakukan pembayaran tunai saat tiba di hotel.'
        );
    }

    /**
     * Browser return from DOKU. This is NOT proof of payment — the booking is
     * only confirmed by a verified server-to-server notification.
     */
    public function callback(Request $request, PaymentCallbackToken $callbackToken): View
    {
        $code = (string) $request->query('code');
        $booking = $code !== '' ? Booking::where('code', $code)->first() : null;

        if ($booking) {
            $hasAccess = $this->authorizeBookingAccess($request, $booking, abort: false);

            // The redirect back from DOKU can land on a different host than
            // checkout, so the session grant may be gone. A valid callback token
            // re-proves this browser is the payer and restores that grant, so
            // the page (and its status poller) work instead of showing a
            // permanent "waiting" screen for an already-paid booking.
            if (! $hasAccess && $callbackToken->isValid($request->query('t'), $booking->code)) {
                $request->session()->put('booking_access.'.$booking->code, $booking->customer_email);
                $hasAccess = true;
            }

            if (! $hasAccess) {
                $booking = null;
            }
        }

        return view('public.payment.callback', compact('booking'));
    }

    /**
     * Lightweight status poll for the "waiting for confirmation" pages.
     *
     * The payment callback and booking pages render once, but confirmation
     * arrives later via the server-to-server notification. The page polls this
     * endpoint so it can refresh itself the moment the status actually changes,
     * instead of the guest staring at a stale "sedang diperiksa" screen.
     *
     * Guarded by the same access rule as the booking itself — it exposes only
     * the two status values, and only to someone already allowed to see them.
     */
    public function status(Request $request, Booking $booking): JsonResponse
    {
        if (! $this->authorizeBookingAccess($request, $booking, abort: false)) {
            return response()->json(['message' => 'forbidden'], 403);
        }

        return response()->json([
            'status' => $booking->status->value,
            'payment_status' => $booking->payment_status->value,
        ]);
    }

    private function authorizeBookingAccess(Request $request, Booking $booking, bool $abort = true): bool
    {
        $user = $request->user();

        if ($user && ($user->isStaff() || $booking->user_id === $user->id)) {
            return true;
        }

        $granted = $request->session()->get('booking_access.'.$booking->code);
        $ok = $granted && $granted === $booking->customer_email;

        if (! $ok && $abort) {
            abort(403, 'Silakan verifikasi kode pemesanan dan email Anda.');
        }

        return (bool) $ok;
    }
}
