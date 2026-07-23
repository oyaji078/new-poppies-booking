<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            RoomSeeder::class,
            InventorySeeder::class,
            FaqSeeder::class,
            PageSeeder::class,
        ]);

        $credentials = [
            'name' => env('ADMIN_NAME', 'Administrator'),
            'email' => env('ADMIN_EMAIL'),
            'password' => env('ADMIN_PASSWORD'),
        ];

        $validator = Validator::make($credentials, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException(
                'ADMIN_EMAIL and ADMIN_PASSWORD (minimum 12 characters) are required for production deployment.'
            );
        }

        User::updateOrCreate(
            ['email' => $credentials['email']],
            [
                'name' => $credentials['name'],
                'password' => Hash::make($credentials['password']),
                'role' => UserRole::SUPER_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
