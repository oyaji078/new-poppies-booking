<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Development-only demo accounts. Credentials must never be reused in
     * production.
     *
     * Two accounts because the roles are deliberately disjoint: the Super Admin
     * owns system/money configuration, the Admin owns daily operations. Seeding
     * both means a fresh install can immediately do both without editing the DB.
     * Promote a real account with `php artisan user:make-superadmin <email>` and
     * remove these demo users before going live.
     */
    public function run(): void
    {
        $accounts = [
            ['superadmin@newpoppiessenggigi.test', 'Super Admin', UserRole::SUPER_ADMIN],
            ['admin@newpoppiessenggigi.test', 'Administrator', UserRole::ADMIN],
        ];

        foreach ($accounts as [$email, $name, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'role' => $role,
                    'is_active' => true,
                    'phone' => '081900000000',
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
