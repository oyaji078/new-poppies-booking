<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'phone',
        'country',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isAdmin(): bool
    {
        // A super admin is an admin with more, never less.
        return in_array($this->role, [UserRole::ADMIN, UserRole::SUPER_ADMIN], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role instanceof UserRole && $this->role->isSuperAdmin();
    }

    public function isStaff(): bool
    {
        return $this->role instanceof UserRole && $this->role->isStaff();
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::CUSTOMER;
    }
}
