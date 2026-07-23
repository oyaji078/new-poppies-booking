<?php

namespace App\Services\Doku;

/**
 * Strips secrets and PII-heavy fields before a DOKU payload is persisted for
 * audit. We keep enough to debug a transaction, never enough to leak a card or
 * a credential.
 */
class DokuPayloadRedactor
{
    /** @var list<string> */
    private array $secretKeys = [
        'signature', 'digest', 'secret_key', 'client_secret', 'token', 'token_id',
        'card_number', 'card_no', 'cvv', 'pan', 'authorization',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->redact($value);

                continue;
            }

            if (in_array(strtolower((string) $key), $this->secretKeys, true)) {
                $payload[$key] = '[REDACTED]';
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    public function redactHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            $out[$name] = in_array(strtolower($name), ['signature', 'digest', 'authorization'], true)
                ? '[REDACTED]'
                : (is_array($value) ? implode(',', $value) : (string) $value);
        }

        return $out;
    }
}
