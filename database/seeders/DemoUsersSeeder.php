<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development/demo accounts, one per role. NEVER seed these in production —
 * the passwords are public. Run: php artisan db:seed --class=DemoUsersSeeder
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $accounts = [
            ['superadmin@newpoppiessenggigi.test', 'Super Admin Demo', UserRole::SUPER_ADMIN],
            ['admin@newpoppiessenggigi.test', 'Admin Demo', UserRole::ADMIN],
            ['pelanggan@newpoppiessenggigi.test', 'Pelanggan Demo', UserRole::CUSTOMER],
        ];

        foreach ($accounts as [$email, $name, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => $role,
                    'is_active' => true,
                    'phone' => '081900000000',
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
