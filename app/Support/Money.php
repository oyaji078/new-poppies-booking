<?php

namespace App\Support;

/**
 * Money is always stored and passed around as an integer number of rupiah.
 * This helper only formats for display — it never participates in calculations.
 */
class Money
{
    public static function format(int|float|null $amount, bool $withSymbol = true): string
    {
        $amount = (int) round((float) $amount);
        $formatted = number_format($amount, 0, ',', '.');

        return $withSymbol ? 'Rp '.$formatted : $formatted;
    }
}
