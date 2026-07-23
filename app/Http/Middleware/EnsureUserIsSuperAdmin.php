<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the settings that decide where real money goes.
 *
 * Staff — including ordinary admins — must not be able to point the payment
 * gateway at sandbox (bookings would confirm without anyone paying) or at
 * production (test bookings would charge real cards). Only a super admin may.
 */
class EnsureUserIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Halaman ini hanya dapat diakses oleh Super Admin.');
        }

        return $next($request);
    }
}
