<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Doku\DokuNotificationService;
use App\Services\Doku\DokuNotificationVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * DOKU server-to-server payment notification endpoint.
 *
 * CSRF-exempt (see bootstrap/app.php) because it is machine-to-machine; it is
 * authenticated by DOKU signature verification instead.
 */
class DokuNotificationController extends Controller
{
    public function __invoke(
        Request $request,
        DokuNotificationVerifier $verifier,
        DokuNotificationService $service,
    ): JsonResponse {
        // 1) Read the RAW body — the signature is computed over these exact bytes.
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        // 2-4) Validate headers, client id and signature.
        $verification = $verifier->verify($request);

        // A request without the signature headers cannot be from DOKU at all, and
        // carries no Request-Id for the idempotency key to bite on. Reject it
        // before it can write anything.
        if ($verification['reason'] === 'missing_headers') {
            Log::warning('DOKU notification without signature headers rejected', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Missing signature headers'], 400);
        }

        $result = $service->handle(
            payload: $payload,
            rawBody: $rawBody,
            signatureValid: (bool) $verification['valid'],
            requestId: $verification['request_id'],
        );

        // A rejected signature must not look like a success.
        if ($result === 'invalid_signature') {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        if ($result === 'unknown_invoice') {
            Log::info('DOKU notification for unknown invoice', ['request_id' => $verification['request_id']]);

            return response()->json(['message' => 'Unknown invoice'], 404);
        }

        // Duplicates return 200 so DOKU stops retrying — the first one already won.
        return response()->json(['message' => 'OK', 'result' => $result], 200);
    }
}
