<?php

namespace Database\Seeders;

use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Models\RoomType;
use App\Models\RoomTypeInventory;
use App\Models\SeasonalRate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $start = CarbonImmutable::today();
        $days = 120; // > 90 days of forward inventory

        foreach (RoomType::all() as $roomType) {
            $rows = [];
            for ($i = 0; $i < $days; $i++) {
                $date = $start->addDays($i)->toDateString();
                $rows[] = [
                    'room_type_id' => $roomType->id,
                    'inventory_date' => $date,
                    'total_inventory' => $roomType->default_inventory,
                    'blocked_inventory' => 0,
                    'held_inventory' => 0,
                    'confirmed_inventory' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Idempotent upsert on the unique (room_type_id, inventory_date) key.
            foreach (array_chunk($rows, 100) as $chunk) {
                RoomTypeInventory::upsert($chunk, ['room_type_id', 'inventory_date'], ['total_inventory']);
            }
        }

        // High-season adjustment example (applies to all room types).
        SeasonalRate::updateOrCreate(
            ['name' => 'High Season (Liburan)'],
            [
                'room_type_id' => null,
                'start_date' => $start->addDays(30)->toDateString(),
                'end_date' => $start->addDays(45)->toDateString(),
                'adjustment_percent' => 25,
                'is_active' => true,
            ],
        );

        // Example promotions.
        Promotion::updateOrCreate(
            ['code' => 'WELCOME10'],
            [
                'name' => 'Diskon Selamat Datang 10%',
                'type' => PromotionType::PERCENTAGE,
                'value' => 10,
                'max_discount' => 300_000,
                'is_automatic' => false,
                'min_nights' => 1,
                'min_transaction' => 0,
                'usage_limit' => 1000,
                'is_active' => true,
            ],
        );

        Promotion::updateOrCreate(
            ['code' => null, 'name' => 'Promo Menginap 3 Malam'],
            [
                'type' => PromotionType::FIXED,
                'value' => 200_000,
                'is_automatic' => true,
                'min_nights' => 3,
                'min_transaction' => 0,
                'is_active' => true,
            ],
        );
    }
}
