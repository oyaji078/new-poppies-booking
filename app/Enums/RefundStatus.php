<?php

namespace App\Enums;

enum RefundStatus: string
{
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PROCESSING = 'processing';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';

    public function isComplete(): bool
    {
        return $this === self::SUCCEEDED;
    }

    /**
     * A refund only reduces the paid amount once it has actually succeeded.
     */
    public function reducesPaidAmount(): bool
    {
        return $this === self::SUCCEEDED;
    }

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => 'Diajukan',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::PROCESSING => 'Diproses',
            self::SUCCEEDED => 'Berhasil',
            self::FAILED => 'Gagal',
        };
    }
}
