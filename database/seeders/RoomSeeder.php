<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $amenityNames = [
            'Wi-Fi Gratis' => 'connectivity',
            'AC' => 'comfort',
            'TV Kabel' => 'entertainment',
            'Air Panas' => 'bathroom',
            'Minibar' => 'comfort',
            'Balkon' => 'comfort',
            'Brankas' => 'safety',
            'Pembuat Kopi' => 'comfort',
        ];

        $amenities = collect($amenityNames)->map(fn ($category, $name) => Amenity::updateOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'category' => $category],
        ));

        $types = [
            [
                'name' => 'Standard Room',
                'short_description' => 'Kamar nyaman dengan fasilitas lengkap untuk menginap hemat.',
                'base_price' => 550_000,
                'adult_capacity' => 2, 'child_capacity' => 1, 'max_guests' => 3,
                'bed_type' => '1 Queen Bed', 'room_size' => 24, 'default_inventory' => 6,
                'rooms' => ['STD-101', 'STD-102', 'STD-103', 'STD-104', 'STD-105', 'STD-106'],
            ],
            [
                'name' => 'Deluxe Room',
                'short_description' => 'Lebih luas dengan balkon menghadap taman tropis.',
                'base_price' => 850_000,
                'adult_capacity' => 2, 'child_capacity' => 2, 'max_guests' => 4,
                'bed_type' => '1 King Bed', 'room_size' => 32, 'default_inventory' => 4,
                'rooms' => ['DLX-201', 'DLX-202', 'DLX-203', 'DLX-204'],
            ],
            [
                'name' => 'Family Room',
                'short_description' => 'Kamar keluarga luas dengan pemandangan laut Senggigi.',
                'base_price' => 1_250_000,
                'adult_capacity' => 4, 'child_capacity' => 2, 'max_guests' => 6,
                'bed_type' => '2 Queen Beds', 'room_size' => 48, 'default_inventory' => 3,
                'rooms' => ['FAM-301', 'FAM-302', 'FAM-303'],
            ],
        ];

        foreach ($types as $i => $data) {
            $roomNumbers = $data['rooms'];
            unset($data['rooms']);

            $roomType = RoomType::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                array_merge($data, [
                    'full_description' => 'Kamar '.$data['name'].' di New Poppies Senggigi dirancang untuk kenyamanan Anda, '
                        .'dilengkapi fasilitas modern dan pelayanan ramah khas Lombok. Nikmati suasana tenang tepi pantai '
                        .'dengan akses mudah ke berbagai destinasi wisata Senggigi.',
                    'policies' => 'Check-in mulai 14.00 WITA, check-out maksimal 12.00 WITA. Bebas biaya pembatalan hingga 24 jam sebelum kedatangan. Dilarang merokok di dalam kamar.',
                    'is_published' => true,
                    'sort_order' => $i,
                ]),
            );

            $roomType->amenities()->sync($amenities->pluck('id'));

            foreach ($roomNumbers as $number) {
                $digits = (int) preg_replace('/\D/', '', $number);
                Room::updateOrCreate(
                    ['room_number' => $number],
                    [
                        'room_type_id' => $roomType->id,
                        'floor' => (string) max(1, intdiv($digits, 100)),
                        'is_active' => true,
                        'under_maintenance' => false,
                    ],
                );
            }
        }
    }
}
