<?php

namespace App\Services\Doku;

use App\Models\Booking;
use Illuminate\Support\Facades\Log;

/**
 * A short-lived, URL-safe token that proves a browser is the one DOKU just sent
 * back for a specific booking.
 *
 * The payment callback runs after a redirect through DOKU — often onto a
 * different host than the one where checkout began (localhost vs a tunnel, or a
 * bare domain vs www) — so the session cookie cannot be relied on to survive.
 * Without proof of identity the callback can only show a generic "waiting"
 * screen forever, even for a booking that is already paid.
 *
 * This token travels in the callback URL and is verified with HMAC over the app
 * key, so it grants access to exactly one booking, for a short window, and
 * cannot be forged. It never exposes anything itself — it only re-establishes
 * the access the guest already had at checkout.
 */
class PaymentCallbackToken
{
    /** How long the token stays valid after the payment page is created. */
    private const TTL_MINUTES = 180;

    public function for(Booking $booking): string
    {
        $payload = $booking->code.'|'.(now()->timestamp + self::TTL_MINUTES * 60);

        return $this->base64Url($payload.'|'.$this->sign($payload));
    }

    /**
     * True only if the token is well-formed, not expired, signed with our key,
     * and issued for this exact booking code.
     */
    public function isValid(?string $token, string $code): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $decoded = $this->base64UrlDecode($token);
        $parts = explode('|', $decoded);

        if (count($parts) !== 3) {
            return false;
        }

        [$tokenCode, $expiry, $signature] = $parts;
        $payload = $tokenCode.'|'.$expiry;

        // Constant-time compare; a bad signature must not be distinguishable by timing.
        if (! hash_equals($this->sign($payload), $signature)) {
            return false;
        }

        if (! ctype_digit($expiry) || (int) $expiry < now()->timestamp) {
            return false;
        }

        if (! hash_equals($code, $tokenCode)) {
            Log::warning('Payment callback token used for the wrong booking', [
                'token_code' => $tokenCode,
                'requested_code' => $code,
            ]);

            return false;
        }

        return true;
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
