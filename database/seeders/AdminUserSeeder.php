<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Development-only demo admin. Credentials must never be reused in production.
     *
     * Seeded as SUPER_ADMIN so a fresh install has someone who can reach the
     * DOKU mode switcher; otherwise the payment gateway could only be changed by
     * editing the database by hand. Promote a real account with
     * `php artisan user:make-superadmin <email>` and remove this demo user.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@newpoppiessenggigi.test'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => UserRole::SUPER_ADMIN,
                'phone' => '081900000000',
                'email_verified_at' => now(),
            ],
        );
    }
}
