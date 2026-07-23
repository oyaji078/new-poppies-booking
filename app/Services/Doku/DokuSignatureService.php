<?php

namespace App\Services\Doku;

/**
 * DOKU non-SNAP signature generation and verification.
 *
 * Component string (exact order, one per line, NO trailing newline):
 *
 *   Client-Id:<client id>
 *   Request-Id:<request id>
 *   Request-Timestamp:<ISO8601 UTC, e.g. 2026-07-18T08:45:42Z>
 *   Request-Target:<path only, e.g. /checkout/v1/payment>
 *   Digest:<base64(sha256(raw json body))>
 *
 * Signature header value = "HMACSHA256=" . base64(hmac_sha256(components, secret))
 *
 * For INBOUND notifications the same construction is used, except Request-Target
 * is the path of the merchant's own notification URL.
 *
 * @see https://developers.doku.com/get-started-with-doku-api/signature-component/non-snap/signature-component-from-request-header
 */
class DokuSignatureService
{
    public function __construct(
        private readonly ?string $clientId = null,
        private readonly ?string $secretKey = null,
    ) {}

    private function clientId(): string
    {
        return (string) ($this->clientId ?? config('doku.client_id'));
    }

    private function secretKey(): string
    {
        return (string) ($this->secretKey ?? config('doku.secret_key'));
    }

    /**
     * Digest = base64( sha256( raw body ) ). Must be computed over the EXACT raw
     * bytes that are sent/received — never a re-encoded array.
     */
    public function digest(string $rawBody): string
    {
        return base64_encode(hash('sha256', $rawBody, true));
    }

    /**
     * Build the newline-separated component string. No trailing newline.
     */
    public function componentString(
        string $requestId,
        string $timestamp,
        string $requestTarget,
        ?string $digest,
        ?string $clientId = null,
    ): string {
        $lines = [
            'Client-Id:'.($clientId ?? $this->clientId()),
            'Request-Id:'.$requestId,
            'Request-Timestamp:'.$timestamp,
            'Request-Target:'.$requestTarget,
        ];

        // GET requests carry no body and therefore no Digest line.
        if ($digest !== null && $digest !== '') {
            $lines[] = 'Digest:'.$digest;
        }

        return implode("\n", $lines);
    }

    /**
     * Full Signature header value, including the HMACSHA256= prefix.
     */
    public function sign(
        string $requestId,
        string $timestamp,
        string $requestTarget,
        ?string $digest,
        ?string $clientId = null,
    ): string {
        $components = $this->componentString($requestId, $timestamp, $requestTarget, $digest, $clientId);

        return 'HMACSHA256='.base64_encode(
            hash_hmac('sha256', $components, $this->secretKey(), true)
        );
    }

    /**
     * Constant-time comparison of an incoming Signature header against the
     * signature we recompute locally.
     */
    public function verify(
        string $incomingSignature,
        string $requestId,
        string $timestamp,
        string $requestTarget,
        ?string $digest,
        ?string $clientId = null,
    ): bool {
        if ($this->secretKey() === '') {
            return false;
        }

        $expected = $this->sign($requestId, $timestamp, $requestTarget, $digest, $clientId);

        return hash_equals($expected, trim($incomingSignature));
    }

    /**
     * ISO8601 UTC timestamp in the format DOKU expects (no microseconds).
     */
    public function timestamp(): string
    {
        return now()->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
