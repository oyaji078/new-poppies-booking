<?php

namespace Tests\Unit\Doku;

use App\Services\Doku\DokuSignatureService;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the signature construction against DOKU's documented algorithm:
 *   Digest    = base64(sha256(raw body))
 *   Component = "Client-Id:..\nRequest-Id:..\nRequest-Timestamp:..\nRequest-Target:..\nDigest:.."
 *   Signature = "HMACSHA256=" . base64(hmac_sha256(component, secret))
 */
class DokuSignatureServiceTest extends TestCase
{
    private const CLIENT_ID = 'BRN-0253-1783902950258';

    private const SECRET = 'SK-testsecret1234567890';

    private function service(): DokuSignatureService
    {
        return new DokuSignatureService(self::CLIENT_ID, self::SECRET);
    }

    public function test_digest_is_base64_of_sha256_of_raw_body(): void
    {
        $body = '{"order":{"amount":20000,"invoice_number":"INV-1"}}';

        $expected = base64_encode(hash('sha256', $body, true));

        $this->assertSame($expected, $this->service()->digest($body));
    }

    public function test_digest_of_empty_body_matches_sha256_of_empty_string(): void
    {
        $this->assertSame(
            base64_encode(hash('sha256', '', true)),
            $this->service()->digest('')
        );
    }

    public function test_component_string_layout_and_order_with_no_trailing_newline(): void
    {
        $components = $this->service()->componentString(
            requestId: 'req-123',
            timestamp: '2026-07-18T08:45:42Z',
            requestTarget: '/checkout/v1/payment',
            digest: 'DIGESTVALUE',
        );

        $expected = 'Client-Id:'.self::CLIENT_ID."\n"
            ."Request-Id:req-123\n"
            ."Request-Timestamp:2026-07-18T08:45:42Z\n"
            ."Request-Target:/checkout/v1/payment\n"
            .'Digest:DIGESTVALUE';

        $this->assertSame($expected, $components);
        $this->assertStringEndsNotWith("\n", $components);
    }

    public function test_digest_line_is_omitted_when_there_is_no_body(): void
    {
        $components = $this->service()->componentString(
            requestId: 'req-123',
            timestamp: '2026-07-18T08:45:42Z',
            requestTarget: '/orders/v1/status/INV-1',
            digest: null,
        );

        $this->assertStringNotContainsString('Digest:', $components);
        $this->assertStringEndsWith('Request-Target:/orders/v1/status/INV-1', $components);
    }

    public function test_signature_is_prefixed_hmac_sha256_base64(): void
    {
        $service = $this->service();
        $components = $service->componentString('req-123', '2026-07-18T08:45:42Z', '/checkout/v1/payment', 'DIGESTVALUE');

        $expected = 'HMACSHA256='.base64_encode(hash_hmac('sha256', $components, self::SECRET, true));

        $this->assertSame(
            $expected,
            $service->sign('req-123', '2026-07-18T08:45:42Z', '/checkout/v1/payment', 'DIGESTVALUE')
        );
    }

    public function test_verify_accepts_a_correctly_generated_signature(): void
    {
        $service = $this->service();
        $signature = $service->sign('req-9', '2026-07-18T08:45:42Z', '/api/payments/doku/notifications', 'D1');

        $this->assertTrue(
            $service->verify($signature, 'req-9', '2026-07-18T08:45:42Z', '/api/payments/doku/notifications', 'D1')
        );
    }

    public function test_verify_rejects_a_tampered_body_digest(): void
    {
        $service = $this->service();
        $signature = $service->sign('req-9', '2026-07-18T08:45:42Z', '/api/payments/doku/notifications', 'D1');

        // Attacker changed the body, so the digest no longer matches.
        $this->assertFalse(
            $service->verify($signature, 'req-9', '2026-07-18T08:45:42Z', '/api/payments/doku/notifications', 'TAMPERED')
        );
    }

    public function test_verify_rejects_a_different_request_target(): void
    {
        $service = $this->service();
        $signature = $service->sign('req-9', '2026-07-18T08:45:42Z', '/api/payments/doku/notifications', 'D1');

        $this->assertFalse(
            $service->verify($signature, 'req-9', '2026-07-18T08:45:42Z', '/somewhere/else', 'D1')
        );
    }

    public function test_verify_fails_when_secret_key_is_missing(): void
    {
        $service = new DokuSignatureService(self::CLIENT_ID, '');

        $this->assertFalse($service->verify('HMACSHA256=whatever', 'r', 't', '/x', 'd'));
    }

    public function test_timestamp_is_iso8601_utc(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $this->service()->timestamp()
        );
    }
}
