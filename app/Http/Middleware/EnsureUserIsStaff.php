<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the admin/back-office area. Only staff roles (admin, receptionist,
 * manager) may pass; everyone else gets a 403.
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // A staff account disabled mid-session loses access immediately.
        if (! $user || ! $user->isStaff() || $user->is_active === false) {
            abort(403, 'Anda tidak memiliki akses ke area ini.');
        }

        return $next($request);
    }
}
