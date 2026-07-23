<?php

namespace App\Services\Doku;

/**
 * Normalises a DOKU notification payload into the values we act on.
 *
 * Documented notification fields used:
 *   order.invoice_number, order.amount, transaction.status, transaction.date,
 *   service.id, acquirer.id, channel.id
 *
 * Any transaction.status that is not explicitly mapped in config('doku.status_map')
 * resolves to 'review' — an unrecognised status must NEVER confirm a booking.
 */
class DokuPaymentMapper
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function invoiceNumber(array $payload): ?string
    {
        $value = data_get($payload, 'order.invoice_number');

        return $value !== null ? (string) $value : null;
    }

    /**
     * DOKU may send the amount as a string or number; always normalise to
     * integer rupiah for comparison against the booking total.
     *
     * @param  array<string, mixed>  $payload
     */
    public function amount(array $payload): ?int
    {
        $value = data_get($payload, 'order.amount');

        if ($value === null || $value === '') {
            return null;
        }

        // Guard against "20000.00" style values without losing rupiah precision.
        return (int) round((float) $value);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function currency(array $payload): string
    {
        return (string) (data_get($payload, 'order.currency') ?? config('doku.currency', 'IDR'));
    }

    /**
     * Raw provider status, upper-cased (e.g. SUCCESS, FAILED, PENDING).
     *
     * @param  array<string, mixed>  $payload
     */
    public function rawStatus(array $payload): string
    {
        return strtoupper((string) (data_get($payload, 'transaction.status') ?? ''));
    }

    /**
     * Internal status: paid | pending | failed | expired | refunded | review.
     *
     * @param  array<string, mixed>  $payload
     */
    public function status(array $payload): string
    {
        $map = (array) config('doku.status_map', []);

        return $map[$this->rawStatus($payload)] ?? 'review';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function eventType(array $payload): string
    {
        $service = (string) (data_get($payload, 'service.id') ?? 'UNKNOWN');

        return $service.'.'.($this->rawStatus($payload) ?: 'UNKNOWN');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function transactionDate(array $payload): ?string
    {
        $value = data_get($payload, 'transaction.date');

        return $value !== null ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function channel(array $payload): ?string
    {
        $value = data_get($payload, 'channel.id') ?? data_get($payload, 'acquirer.id');

        return $value !== null ? (string) $value : null;
    }
}
