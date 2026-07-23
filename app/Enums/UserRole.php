<?php

namespace App\Enums;

/**
 * Application roles. One primary admin role is enough for the thesis version,
 * but the structure stays extendable (receptionist / manager) as required.
 */
enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case CUSTOMER = 'customer';
    case RECEPTIONIST = 'receptionist';
    case MANAGER = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Administrator',
            self::CUSTOMER => 'Customer',
            self::RECEPTIONIST => 'Receptionist',
            self::MANAGER => 'Manager',
        };
    }

    /**
     * Roles that may access the admin/staff back office.
     */
    public function isStaff(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::ADMIN, self::RECEPTIONIST, self::MANAGER], true);
    }

    /**
     * The one role allowed to touch settings that move real money — currently
     * switching DOKU between sandbox and production.
     */
    public function isSuperAdmin(): bool
    {
        return $this === self::SUPER_ADMIN;
    }
}
