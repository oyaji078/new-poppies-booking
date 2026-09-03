<?php

namespace App\Enums;

/**
 * Canonical audit action names (§33). Kept as an enum so call sites don't drift
 * into inconsistent free-text strings.
 */
enum AuditAction: string
{
    case ADMIN_LOGIN = 'admin.login';
    case ROOM_CHANGE = 'room.change';
    case PRICE_CHANGE = 'price.change';
    case INVENTORY_CHANGE = 'inventory.change';
    case BOOKING_CHANGE = 'booking.change';
    case BOOKING_HOLD_CREATED = 'booking.hold_created';
    case BOOKING_EXPIRED = 'booking.expired';
    case PAYMENT_REVIEW = 'payment.review';
    case PAYMENT_CONFIRMED = 'payment.confirmed';
    case PAYMENT_NOTIFICATION = 'payment.notification';
    case PAYMENT_SIGNATURE_INVALID = 'payment.signature_invalid';
    case CANCELLATION = 'booking.cancellation';
    case REFUND = 'booking.refund';
    case CHECK_IN = 'booking.check_in';
    case CHECK_OUT = 'booking.check_out';
    case NO_SHOW = 'booking.no_show';
    case SETTINGS_CHANGE = 'settings.change';
    case ADMIN_OVERRIDE = 'admin.override';
    case DOKU_ENVIRONMENT_SWITCHED = 'doku.environment_switched';
    case USER_MANAGED = 'user.managed';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN_LOGIN => 'Login admin',
            self::ROOM_CHANGE => 'Perubahan kamar',
            self::PRICE_CHANGE => 'Perubahan harga',
            self::INVENTORY_CHANGE => 'Perubahan inventaris',
            self::BOOKING_CHANGE => 'Perubahan pemesanan',
            self::BOOKING_HOLD_CREATED => 'Hold pemesanan dibuat',
            self::BOOKING_EXPIRED => 'Pemesanan kedaluwarsa',
            self::PAYMENT_REVIEW => 'Pembayaran ditinjau',
            self::PAYMENT_CONFIRMED => 'Pembayaran dikonfirmasi',
            self::PAYMENT_NOTIFICATION => 'Notifikasi pembayaran',
            self::PAYMENT_SIGNATURE_INVALID => 'Signature pembayaran tidak sah',
            self::CANCELLATION => 'Pembatalan',
            self::REFUND => 'Refund',
            self::CHECK_IN => 'Check-in',
            self::CHECK_OUT => 'Check-out',
            self::NO_SHOW => 'Tidak hadir',
            self::SETTINGS_CHANGE => 'Perubahan pengaturan',
            self::ADMIN_OVERRIDE => 'Override admin',
            self::DOKU_ENVIRONMENT_SWITCHED => 'Mode DOKU diubah',
            self::USER_MANAGED => 'Manajemen pengguna',
        };
    }

    /**
     * Actions that deserve to stand out in the log: money, access, and overrides.
     */
    public function isSensitive(): bool
    {
        return in_array($this, [
            self::PAYMENT_SIGNATURE_INVALID,
            self::ADMIN_OVERRIDE,
            self::DOKU_ENVIRONMENT_SWITCHED,
            self::USER_MANAGED,
            self::REFUND,
            self::SETTINGS_CHANGE,
        ], true);
    }
}
