<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use App\Services\Booking\BookingExpirationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpireBookingHoldsController extends Controller
{
    public function __invoke(Request $request, BookingExpirationService $expiration): JsonResponse
    {
        $secret = (string) config('services.vercel.cron_secret', '');
        $authorization = (string) $request->header('Authorization', '');

        if ($secret === '' || ! hash_equals('Bearer '.$secret, $authorization)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'status' => 'ok',
            'expired' => $expiration->expireDueHolds(),
        ]);
    }
}
