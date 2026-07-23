<?php

namespace App\Services\Booking;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Public booking code, e.g. NPS-20260810-A7K9P2.
 *
 * Deliberately unpredictable and unrelated to the auto-increment id so the code
 * can be shared with guests without leaking how many bookings exist.
 */
class BookingCodeGenerator
{
    /** Ambiguous characters (0/O, 1/I/L) removed to keep codes readable aloud. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const SUFFIX_LENGTH = 6;

    public function generate(?CarbonImmutable $date = null): string
    {
        $date ??= CarbonImmutable::now();
        $datePart = $date->format('Ymd');

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = sprintf('NPS-%s-%s', $datePart, $this->randomSuffix());

            if (! Booking::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Gagal membuat kode pemesanan unik.');
    }

    private function randomSuffix(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $suffix = '';

        for ($i = 0; $i < self::SUFFIX_LENGTH; $i++) {
            $suffix .= self::ALPHABET[random_int(0, $max)];
        }

        return $suffix;
    }
}
