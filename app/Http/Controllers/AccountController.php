<?php

namespace App\Http\Controllers;

use App\Services\Operations\CancellationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in guest's own area: their booking history.
 *
 * Only bookings owned by the authenticated account are listed — this is the
 * account-based path, distinct from the code+email lookup a walk-up guest uses.
 */
class AccountController extends Controller
{
    public function bookings(Request $request, CancellationService $cancellation): View
    {
        $user = $request->user();

        $bookings = $user->bookings()
            ->with(['items'])
            ->latest('id')
            ->paginate(10);

        // Per-booking flags the card needs, computed once here.
        $bookings->getCollection()->transform(function ($booking) use ($cancellation) {
            $booking->can_cancel = $cancellation->canCancel($booking)['allowed'];
            $booking->is_payable = $booking->isPayable();

            return $booking;
        });

        return view('public.account.bookings', compact('bookings', 'user'));
    }
}
