# New Poppies Senggigi — Sistem Reservasi Hotel

Sistem pemesanan kamar online untuk **New Poppies Senggigi** (Senggigi, Lombok Barat, NTB).
Dibangun dengan Laravel + Livewire + Tailwind, database MySQL/MariaDB (InnoDB), dan
pembayaran online melalui **DOKU Checkout**.

> Zona waktu operasional: **Asia/Makassar (WITA)**. Bahasa antarmuka: **Indonesia**.
> Kode, tabel, dan kolom database menggunakan bahasa Inggris.

---

## 1. Fitur Utama

**Publik**
- Beranda, daftar & detail tipe kamar, galeri, FAQ
- Pencarian ketersediaan berdasarkan tanggal dan jumlah tamu
- Checkout 5 langkah dengan total harga dihitung di server
- Booking hold 30 menit + hitung mundur pembayaran
- Pembayaran online via DOKU
- Cek status pemesanan (kode pemesanan **+** email)
- Pembatalan mandiri sesuai kebijakan
- Chatbot FAQ

**Admin**
- Dashboard operasional (semua angka dari database)
- Tipe kamar, kamar fisik, fasilitas, foto, konten website
- Galeri halaman publik (unggah, keterangan, urutan, tampil/sembunyi)
- Promosi (persentase & nominal, otomatis & berkode)
- Reservasi, peninjauan pembayaran
- Front desk satu papan: check-in + penugasan kamar fisik, check-out, no-show.
  Warna kartu mengikuti status — putih (belum check-in), hijau (sudah check-in),
  biru (sudah check-out), merah (tidak hadir).
- Pembatalan & pencatatan refund
- Laporan (8 jenis) + ekspor CSV + tampilan cetak
- Manajemen FAQ chatbot + antrean pertanyaan belum terjawab
- Audit log (`/admin/audit-log`) — pencarian, filter aksi & tanggal, snapshot perubahan

---

## 2. Kebutuhan Sistem

| Komponen | Versi yang digunakan | Catatan |
|----------|---------------------|---------|
| PHP | **8.2+** | Ekstensi: pdo_mysql, mbstring, openssl, fileinfo, ctype, json. `gd` opsional (thumbnail). |
| Laravel | **12.x** | Lihat catatan A1 di `PROJECT_STATUS.md`. |
| Database | **MySQL 8.x / MariaDB 10.4+** | Wajib **InnoDB** (transaksi + row locking). |
| Node.js | **20+** (diuji pada 24) | Untuk build aset Vite. |
| Composer | 2.x | |

---

## 3. Instalasi Lokal

```bash
# 1. Dependensi
composer install
npm install

# 2. Konfigurasi
cp .env.example .env
php artisan key:generate

# 3. Buat database (sesuaikan kredensial di .env)
#    CREATE DATABASE new_poppies_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
#    CREATE DATABASE new_poppies_booking_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 4. Skema + data contoh
php artisan migrate
php artisan db:seed

# 5. Penyimpanan file & aset
# storage:link mempercepat penyajian foto (web server melayani langsung).
# Bila host tidak mengizinkan symlink, foto tetap tampil: route /storage/{path}
# menyajikannya lewat Laravel.
php artisan storage:link
npm run build
```

Menjalankan aplikasi:

```bash
php artisan serve            # http://localhost:8000
php artisan queue:work       # email & pekerjaan antrean
php artisan schedule:work    # kedaluwarsa hold setiap menit (WAJIB)
```

> **Penting:** tanpa scheduler, booking hold yang kedaluwarsa tidak akan melepas
> inventarisnya. Di server produksi gunakan cron (lihat §6).

### Akun demo (hanya untuk pengembangan lokal)

| Peran | Email | Kata sandi |
|-------|-------|-----------|
| Admin | `admin@newpoppiessenggigi.test` | `password` |

**Jangan pernah** memakai kredensial ini di produksi.

---

## 4. Konfigurasi DOKU

Isi pada `.env` (jangan pernah commit kredensial asli):

```env
DOKU_ENVIRONMENT=sandbox
DOKU_CLIENT_ID=BRN-xxxx-xxxxxxxxxxxxx
DOKU_SECRET_KEY=SK-xxxxxxxxxxxxxxxx
DOKU_BASE_URL=https://api-sandbox.doku.com     # produksi: https://api.doku.com
DOKU_NOTIFICATION_URL="${APP_URL}/webhook/doku/notifications"
DOKU_CALLBACK_URL="${APP_URL}/payment/callback"
DOKU_PAYMENT_DUE_MINUTES=30
```

Di dashboard DOKU, set **Notification URL** ke:

```
https://domain-anda.com/webhook/doku/notifications
```

Endpoint ini dikecualikan dari CSRF (server-to-server) dan diamankan dengan
**verifikasi signature DOKU**, bukan token sesi. Path-nya bukan `/api/...`
dengan sengaja: pada host serverless (Vercel) prefiks `/api` dipesan platform
dan tidak pernah sampai ke router Laravel. Path ini juga ikut ditandatangani,
jadi URL yang berbeda membuat **semua** notifikasi gagal verifikasi.

Untuk uji coba lokal, notifikasi memerlukan URL publik (mis. tunnel seperti ngrok),
karena DOKU harus dapat menjangkau server Anda.

### Dua mode: Sandbox & Produksi

Kredensial kedua mode diisi berdampingan pada `.env`
(`DOKU_SANDBOX_*` dan `DOKU_PRODUCTION_*`). Mode yang aktif disimpan di database
dan dialihkan dari **Admin → Mode Pembayaran DOKU** (`/admin/doku`).

Halaman itu **hanya dapat diakses peran Super Admin** — peran admin biasa
mendapat 403 dan tidak melihat menunya. Setiap perpindahan wajib disertai alasan
dan tercatat di audit log. Peran Super Admin hanya dapat diberikan lewat CLI:

```bash
php artisan user:make-superadmin admin@domain-anda.com
```

### Memeriksa konfigurasi

```bash
php artisan doku:check          # periksa seluruh konfigurasi, tanpa panggilan jaringan
php artisan doku:check --ping   # buat checkout uji untuk membuktikan kredensial
```

`doku:check` menangkap kesalahan yang biasanya baru ketahuan saat transaksi gagal:
kredensial sandbox dipakai pada host produksi (dan sebaliknya), notification URL
yang tidak dapat dijangkau DOKU, path notifikasi yang tidak cocok dengan rute
(signature tidak akan pernah cocok), serta `DOKU_PAYMENT_DUE_MINUTES` yang lebih
panjang daripada durasi hold.

Untuk menguji jalur notifikasi tanpa menunggu DOKU — jalur inilah yang benar-benar
mengonfirmasi pemesanan:

```bash
php artisan doku:simulate-notification {invoice}                     # sukses
php artisan doku:simulate-notification {invoice} --amount=1          # → PAYMENT_REVIEW
php artisan doku:simulate-notification {invoice} --invalid-signature # → 401
```

> Pembayaran hanya dianggap sah setelah notifikasi server-to-server terverifikasi.
> Callback di browser **tidak pernah** menjadi bukti pembayaran.

---

## 5. Pengujian

```bash
php artisan test                                  # seluruh suite
php artisan test --filter=ConcurrentBookingTest   # uji anti double-booking
./vendor/bin/pint --test                          # pemeriksaan gaya kode
npm run build                                     # build aset produksi
```

Suite pengujian memakai database `new_poppies_booking_test` (lihat `phpunit.xml`)
agar perilaku penguncian baris InnoDB benar-benar teruji.

---

## 6. Deployment Produksi

1. **Kode & dependensi**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```
2. **Environment**
   ```env
   APP_ENV=production
   APP_DEBUG=false          # WAJIB false
   APP_URL=https://domain-anda.com
   DOKU_ENVIRONMENT=production
   DOKU_BASE_URL=https://api.doku.com
   ```
3. **Optimasi**
   ```bash
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan storage:link   # opsional — lihat catatan di bagian instalasi
   ```
4. **Scheduler (cron)** — wajib, satu baris:
   ```cron
   * * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
   ```
5. **Queue worker** — gunakan supervisor/systemd agar selalu hidup:
   ```ini
   [program:npseng-queue]
   command=php /path/ke/aplikasi/artisan queue:work --tries=3 --timeout=90
   autostart=true
   autorestart=true
   user=www-data
   numprocs=1
   ```
6. **Web server** — arahkan document root ke `public/`, aktifkan **HTTPS**.
7. **Izin folder**
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```
8. **Backup** — jadwalkan dump harian:
   ```bash
   mysqldump -u user -p new_poppies_booking | gzip > backup-$(date +%F).sql.gz
   ```
   Simpan minimal 7 salinan harian di lokasi terpisah. **Jangan** menghapus riwayat
   pemesanan/pembayaran dari database.

### Checklist go-live

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] HTTPS aktif dan dipaksakan
- [ ] Kredensial DOKU produksi terisi, Notification URL terdaftar
- [ ] Cron scheduler berjalan (uji: buat hold, tunggu 30 menit, inventaris kembali)
- [ ] Queue worker berjalan (uji: email konfirmasi terkirim)
- [ ] SMTP produksi terkonfigurasi
- [ ] Kata sandi akun demo diganti / akun dihapus
- [ ] Backup database terjadwal & pernah diuji restore

---

## 7. Struktur Kode

```
app/
├── Console/Commands/      Perintah terjadwal (kedaluwarsa hold, harness uji)
├── Enums/                 Status pemesanan & pembayaran (mesin status)
├── Http/Controllers/      Controller tipis
├── Livewire/              Komponen UI (Admin/, Public/)
├── Models/                Model Eloquent
├── Services/              Logika bisnis
│   ├── Booking/           Ketersediaan, inventaris (locking), hold, kedaluwarsa
│   ├── Pricing/           Harga & promosi
│   ├── Doku/              Signature, checkout, verifikasi notifikasi
│   ├── Operations/        Pembatalan, refund, check-in/out
│   ├── Reports/           Laporan & ekspor
│   ├── Chatbot/           Chatbot FAQ
│   ├── Payments/          Peninjauan pembayaran
│   ├── Settings/          Pengaturan sistem
│   └── Audit/             Audit log
└── Support/               StayPeriod, Money, DTO harga
```

Dokumentasi teknis & skripsi tersedia di [`docs/`](docs/).
Status pengerjaan: [`PROJECT_STATUS.md`](PROJECT_STATUS.md).
