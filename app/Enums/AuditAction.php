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
}
