<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A user-facing booking failure (unavailable rooms, expired hold, bad dates).
 * Messages are written in Indonesian because they surface directly in the UI.
 */
class BookingException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self('Maaf, kamar untuk tanggal yang dipilih sudah tidak tersedia. Silakan pilih tanggal lain.');
    }

    public static function holdExpired(): self
    {
        return new self('Waktu pemesanan Anda telah habis. Silakan mulai pemesanan baru.');
    }

    public static function invalidStay(string $message): self
    {
        return new self($message);
    }

    public static function capacityExceeded(): self
    {
        return new self('Jumlah tamu melebihi kapasitas tipe kamar yang dipilih.');
    }
}
