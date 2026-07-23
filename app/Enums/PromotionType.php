<?php

namespace App\Enums;

enum PromotionType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::PERCENTAGE => 'Diskon Persentase',
            self::FIXED => 'Potongan Tetap',
        };
    }
}
