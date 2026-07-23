<?php

namespace App\Services\Doku;

use Illuminate\Http\Request;

/**
 * Verifies that an inbound notification genuinely came from DOKU.
 *
 * The signature is recomputed from the RAW request body (never a re-encoded
 * array) using the merchant's own notification path as Request-Target.
 */
class DokuNotificationVerifier
{
    public function __construct(private readonly DokuSignatureService $signatures) {}

    /**
     * @return array{valid: bool, reason: ?string, request_id: ?string}
     */
    public function verify(Request $request): array
    {
        $clientId = $request->header('Client-Id');
        $requestId = $request->header('Request-Id');
        $timestamp = $request->header('Request-Timestamp');
        $signature = $request->header('Signature');

        if (! $clientId || ! $requestId || ! $timestamp || ! $signature) {
            return ['valid' => false, 'reason' => 'missing_headers', 'request_id' => $requestId];
        }

        // The notification must be addressed to OUR merchant account.
        if (! hash_equals((string) config('doku.client_id'), (string) $clientId)) {
            return ['valid' => false, 'reason' => 'client_id_mismatch', 'request_id' => $requestId];
        }

        $rawBody = $request->getContent();
        $digest = $this->signatures->digest($rawBody);
        $target = $this->notificationTarget();

        $valid = $this->signatures->verify($signature, $requestId, $timestamp, $target, $digest, $clientId);

        return [
            'valid' => $valid,
            'reason' => $valid ? null : 'signature_mismatch',
            'request_id' => $requestId,
        ];
    }

    /**
     * Request-Target for notifications is the PATH of our configured
     * notification URL, e.g. /webhook/doku/notifications.
     */
    public function notificationTarget(): string
    {
        $configured = (string) config('doku.notification_url');

        if ($configured !== '') {
            $path = parse_url($configured, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                return $path;
            }
        }

        return '/webhook/doku/notifications';
    }
}
