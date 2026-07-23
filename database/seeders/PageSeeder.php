<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'tentang-kami',
                'title' => 'Tentang New Poppies Senggigi',
                'body' => 'New Poppies Senggigi adalah hotel butik tepi pantai di kawasan Senggigi, Lombok Barat. '
                    .'Kami menghadirkan pengalaman menginap yang tenang dengan taman tropis, pemandangan laut, dan '
                    ."pelayanan khas keramahan Lombok.\n\n"
                    .'Berjarak beberapa menit dari Pantai Senggigi, hotel kami menjadi titik awal yang nyaman untuk '
                    .'menjelajahi Gili, air terjun Sendang Gile, hingga desa-desa adat Sasak.',
            ],
            [
                'slug' => 'syarat-dan-ketentuan',
                'title' => 'Syarat & Ketentuan',
                'body' => "1. Pemesanan dianggap sah setelah pembayaran terverifikasi oleh sistem kami.\n"
                    ."2. Kamar ditahan maksimal 30 menit untuk penyelesaian pembayaran.\n"
                    ."3. Check-in mulai pukul 14.00 WITA; check-out paling lambat pukul 12.00 WITA.\n"
                    ."4. Tamu wajib menunjukkan identitas resmi saat check-in.\n"
                    ."5. Pembatalan mengikuti kebijakan pembatalan yang berlaku pada saat pemesanan.\n"
                    .'6. Harga sudah termasuk pajak dan biaya layanan sesuai rincian pada halaman pemesanan.',
            ],
            [
                'slug' => 'kebijakan-privasi',
                'title' => 'Kebijakan Privasi',
                'body' => 'Kami mengumpulkan data pribadi (nama, email, nomor telepon) semata-mata untuk memproses '
                    ."pemesanan dan menghubungi Anda terkait reservasi.\n\n"
                    .'Data pembayaran diproses langsung oleh penyedia pembayaran DOKU; kami tidak menyimpan nomor '
                    .'kartu Anda. Data pemesanan hanya dapat diakses menggunakan kode pemesanan beserta email Anda.',
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(['slug' => $page['slug']], $page + ['is_published' => true]);
        }
    }
}
