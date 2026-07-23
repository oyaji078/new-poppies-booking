<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Hotel identity (public)
            ['hotel_name', 'New Poppies Senggigi', 'string', 'hotel', 'Nama Hotel', true],
            ['hotel_address', 'Jl. Raya Senggigi, Batu Layar, Lombok Barat, NTB', 'string', 'hotel', 'Alamat', true],
            ['hotel_phone', '(0370) 000-000', 'string', 'hotel', 'Telepon', true],
            ['hotel_email', 'reservasi@newpoppiessenggigi.test', 'string', 'hotel', 'Email', true],
            ['check_in_time', '14:00', 'string', 'hotel', 'Jam Check-in', true],
            ['check_out_time', '12:00', 'string', 'hotel', 'Jam Check-out', true],

            // Pricing / financial (private)
            ['tax_percent', '11', 'integer', 'pricing', 'PPN (%)', false],
            ['service_percent', '10', 'integer', 'pricing', 'Service Charge (%)', false],
            ['currency', 'IDR', 'string', 'pricing', 'Mata Uang', true],
            ['weekend_surcharge_percent', '15', 'integer', 'pricing', 'Kenaikan Akhir Pekan (%)', false],
            ['extra_guest_fee', '150000', 'integer', 'pricing', 'Biaya Tamu Tambahan (Rp/malam)', false],

            // Booking policy (private)
            ['booking_hold_minutes', '30', 'integer', 'booking', 'Durasi Hold (menit)', false],
            ['booking_max_nights', '30', 'integer', 'booking', 'Maksimal Malam', false],
            ['free_cancellation_hours', '24', 'integer', 'booking', 'Batas Pembatalan Gratis (jam)', false],
            // Fee kept from a PAID booking cancelled AFTER the free window but
            // before check-in. Refund = paid − (paid × this%). 0 = always full
            // refund, 100 = no refund.
            ['cancellation_fee_percent', '50', 'integer', 'booking', 'Biaya Pembatalan (%)', false],
        ];

        foreach ($settings as [$key, $value, $type, $group, $label, $isPublic]) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'group' => $group, 'label' => $label, 'is_public' => $isPublic],
            );
        }
    }
}
