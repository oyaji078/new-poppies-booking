# 11 — Pemetaan ke Struktur Skripsi

Dokumen ini memetakan artefak sistem ke bab-bab skripsi agar penulisan lebih mudah.
Seluruh isi teknis merujuk pada kode nyata di repositori — tidak ada hasil evaluasi
yang dibuat-buat.

## 11.1 Pemetaan Bab

| Bab Skripsi | Sumber di repositori |
|-------------|----------------------|
| **BAB I — Pendahuluan** | |
| Latar belakang | `01-requirements.md` §1.1 |
| Rumusan masalah | `01-requirements.md` §1.1 (pemesanan ganda, harga manual, laporan) |
| Tujuan & manfaat | `01-requirements.md` §1.2, `README.md` §1 |
| Batasan masalah | `01-requirements.md` §1.2, §1.7 |
| **BAB II — Landasan Teori** | |
| Metode Prototype | `10-prototype-iterations.md` |
| Laravel, Livewire, MVC | `03-system-architecture.md` §3.2 |
| Basis data relasional & InnoDB | `04-database-erd.md` |
| Transaksi & penguncian baris | `02-business-rules.md` §2.3, `05-booking-flow.md` §5.3 |
| Payment gateway (DOKU) | `06-doku-payment-flow.md` |
| **BAB III — Metodologi & Analisis** | |
| Metode penelitian | `10-prototype-iterations.md` |
| Analisis kebutuhan fungsional | `01-requirements.md` §1.4 |
| Analisis kebutuhan non-fungsional | `01-requirements.md` §1.5 |
| Use case diagram | `01-requirements.md` §1.6 |
| Aturan bisnis | `02-business-rules.md` |
| **BAB IV — Perancangan & Implementasi** | |
| Arsitektur sistem | `03-system-architecture.md` §3.3 |
| ERD & struktur tabel | `04-database-erd.md` |
| Activity diagram | `05-booking-flow.md` §5.1 |
| Sequence diagram | `05-booking-flow.md` §5.2, `06-doku-payment-flow.md` §6.3–6.4 |
| State diagram | `02-business-rules.md` §2.6 |
| Implementasi antarmuka | Tangkapan layar (lihat §11.3) |
| Implementasi pembayaran | `06-doku-payment-flow.md` |
| Implementasi keamanan | `07-security.md` |
| **BAB V — Pengujian** | |
| Rencana pengujian | `08-testing-plan.md` §8.2–8.3 |
| Hasil pengujian unit & fitur | `08-testing-plan.md` §8.1 (184 uji lulus) |
| Pengujian konkurensi | `08-testing-plan.md` §8.4 |
| Pengujian penerimaan (UAT) | `10-prototype-iterations.md` templat C *(diisi peneliti)* |
| Evaluasi pengguna | `10-prototype-iterations.md` templat A & B *(diisi peneliti)* |
| **BAB VI — Penutup** | |
| Kesimpulan | Ditulis peneliti berdasarkan Bab V |
| Saran | `07-security.md` §7.8, `08-testing-plan.md` §8.5, `PROJECT_STATUS.md` |

## 11.2 Kontribusi yang Dapat Ditonjolkan

Tiga hal yang paling layak diangkat sebagai kontribusi teknis:

1. **Pencegahan pemesanan ganda yang dibuktikan secara empiris.**
   Bukan sekadar klaim: 4 proses sistem operasi terpisah berebut kamar terakhir
   secara bersamaan, dan hanya satu yang berhasil (`08-testing-plan.md` §8.4).
   Mekanismenya adalah transaksi + `SELECT … FOR UPDATE` dengan urutan penguncian
   deterministik.

2. **Integritas harga melalui snapshot per malam.**
   Harga yang disepakati tamu dibekukan pada `booking_item_nights`, dan jumlah
   seluruh malam sama persis dengan total tagihan. Perubahan tarif di kemudian hari
   tidak dapat mengubah pemesanan lama.

3. **Keandalan pembayaran: verifikasi signature + idempotensi tingkat database.**
   Notifikasi palsu ditolak, notifikasi ganda tidak berefek ganda karena ditolak
   oleh batasan unik pada `payment_events`, dan status penyedia yang tidak dikenal
   tidak pernah mengonfirmasi pemesanan secara otomatis.

## 11.3 Daftar Tangkapan Layar yang Disarankan

Ambil dari sistem yang berjalan dengan data seeder.

**Sisi publik**
1. Beranda (hero + kamar unggulan)
2. Hasil pencarian ketersediaan (menampilkan sisa kamar & total harga)
3. Detail tipe kamar
4. Checkout — langkah data tamu
5. Checkout — langkah tinjauan (rincian harga per malam)
6. Halaman status pemesanan (hitung mundur pembayaran)
7. Halaman "Pembayaran sedang diperiksa"
8. Formulir cek pemesanan (kode + email)
9. Chatbot terbuka dengan jawaban harga
10. Tampilan ponsel (beranda / hasil pencarian)

**Sisi admin**
11. Dashboard (kartu operasional + pendapatan + okupansi)
12. Daftar reservasi dengan filter
13. Kalender inventaris
14. Manajemen tipe kamar (formulir)
15. Front desk — modal check-in dengan pilihan kamar fisik
16. Peninjauan pembayaran
17. Pembatalan & refund
18. Laporan pendapatan
19. Manajemen FAQ + pertanyaan belum terjawab
20. Audit log

**Bukti pengujian**
21. Keluaran `php artisan test` (184 uji lulus)
22. Keluaran `php artisan test --filter=ConcurrentBookingTest`
23. Baris tabel `room_type_inventories` sebelum & sesudah uji konkurensi

## 11.4 Istilah Teknis (Glosarium)

| Istilah | Penjelasan singkat |
|---------|--------------------|
| Hold | Penahanan kamar sementara (30 menit) sambil menunggu pembayaran |
| Inventaris harian | Jumlah kamar yang dapat dijual untuk satu tipe pada satu tanggal |
| Row locking | Penguncian baris database agar tidak diubah proses lain secara bersamaan |
| Idempoten | Operasi yang bila diulang tidak menimbulkan efek tambahan |
| Signature | Tanda tangan kriptografis untuk membuktikan keaslian pesan |
| Snapshot harga | Salinan beku harga saat pemesanan dibuat |
| Webhook / notifikasi | Panggilan dari server DOKU ke server kita saat status pembayaran berubah |
| Oversold | Kondisi kamar terjual melebihi jumlah yang tersedia |
