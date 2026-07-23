<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'Di mana lokasi New Poppies Senggigi?',
                'answer' => 'Kami berada di Jl. Raya Senggigi, Batu Layar, Lombok Barat, Nusa Tenggara Barat — '
                    .'hanya beberapa menit dari Pantai Senggigi, restoran, dan pusat oleh-oleh.',
                'keywords' => 'lokasi, alamat, dimana, di mana, maps, peta, senggigi',
                'category' => 'lokasi', 'priority' => 10,
            ],
            [
                'question' => 'Metode pembayaran apa yang tersedia?',
                'answer' => 'Pembayaran dilakukan secara online melalui gateway pembayaran DOKU. Anda dapat membayar '
                    .'menggunakan virtual account bank, kartu kredit/debit, QRIS, dan e-wallet. Pemesanan otomatis '
                    .'terkonfirmasi setelah pembayaran terverifikasi oleh sistem kami.',
                'keywords' => 'pembayaran, bayar, metode, doku, transfer, kartu, qris, ewallet, e-wallet, virtual account',
                'category' => 'pembayaran', 'priority' => 10,
            ],
            [
                'question' => 'Bagaimana kebijakan pembatalan?',
                'answer' => 'Pembatalan minimal 24 jam sebelum tanggal check-in berhak atas pengembalian dana penuh '
                    .'sesuai kebijakan. Pembatalan kurang dari 24 jam sebelum check-in tidak memperoleh pengembalian '
                    .'dana otomatis. Pengembalian dana diproses oleh tim kami setelah pembatalan dikonfirmasi.',
                'keywords' => 'pembatalan, batal, cancel, refund, uang kembali, pengembalian',
                'category' => 'kebijakan', 'priority' => 10,
            ],
            [
                'question' => 'Bagaimana cara memesan kamar?',
                'answer' => 'Pilih menu “Cari & Pesan”, masukkan tanggal menginap dan jumlah tamu, lalu pilih tipe kamar '
                    .'yang tersedia. Isi data tamu, tinjau rincian harga, lalu lanjutkan ke pembayaran. Kamar akan '
                    .'ditahan selama 30 menit untuk Anda menyelesaikan pembayaran.',
                'keywords' => 'cara pesan, memesan, booking, reservasi, pesan kamar, cara booking',
                'category' => 'pemesanan', 'priority' => 9,
            ],
            [
                'question' => 'Bagaimana cara mengecek status pemesanan saya?',
                'answer' => 'Buka menu “Cek Pemesanan”, lalu masukkan kode pemesanan (contoh: NPS-20260810-A7K9P2) '
                    .'beserta email yang Anda gunakan saat memesan. Demi keamanan, kode pemesanan saja tidak cukup '
                    .'untuk menampilkan detail pemesanan.',
                'keywords' => 'cek pemesanan, status, kode pemesanan, lacak, cek booking',
                'category' => 'pemesanan', 'priority' => 9,
            ],
            [
                'question' => 'Fasilitas apa saja yang tersedia di hotel?',
                'answer' => 'Kami menyediakan Wi-Fi gratis, kolam renang, restoran, taman tropis, area parkir luas, '
                    .'layanan antar-jemput, resepsionis 24 jam, dan akses mudah ke pantai.',
                'keywords' => 'fasilitas, wifi, kolam renang, restoran, parkir, sarapan, amenitas',
                'category' => 'fasilitas', 'priority' => 8,
            ],
            [
                'question' => 'Bagaimana cara menghubungi hotel?',
                'answer' => 'Anda dapat menghubungi kami melalui telepon (0370) 000-000 atau email '
                    .'reservasi@newpoppiessenggigi.test. Resepsionis kami tersedia 24 jam.',
                'keywords' => 'kontak, hubungi, telepon, nomor, email, whatsapp, cs',
                'category' => 'kontak', 'priority' => 8,
            ],
            [
                'question' => 'Apakah tersedia antar-jemput bandara?',
                'answer' => 'Ya, kami menyediakan layanan antar-jemput dari/ke Bandara Internasional Lombok (LOP) '
                    .'dengan biaya tambahan. Silakan sampaikan permintaan Anda pada kolom permintaan khusus saat '
                    .'memesan, atau hubungi kami langsung.',
                'keywords' => 'antar jemput, jemput, bandara, airport, shuttle, transportasi',
                'category' => 'fasilitas', 'priority' => 7,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                $faq + ['is_active' => true],
            );
        }
    }
}
