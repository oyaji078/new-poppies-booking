<?php

use App\Support\Money;

if (! function_exists('rupiah')) {
    /**
     * Format an integer-rupiah amount for display, e.g. rupiah(1500000) => "Rp 1.500.000".
     */
    function rupiah(int|float|null $amount, bool $withSymbol = true): string
    {
        return Money::format($amount, $withSymbol);
    }
}
