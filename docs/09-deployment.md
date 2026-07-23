# 09 — Panduan Deployment

Panduan instalasi lokal dan produksi lengkap ada di [`../README.md`](../README.md)
(§3 dan §6). Dokumen ini merangkum hal-hal khusus operasional.

## 9.1 Komponen Runtime Wajib

| Komponen | Perintah | Akibat bila tidak jalan |
|----------|----------|-------------------------|
| Web server | document root → `public/` | Situs tidak dapat diakses |
| Scheduler | cron `* * * * * php artisan schedule:run` | **Hold kedaluwarsa tidak melepas inventaris** → kamar terkunci selamanya |
| Queue worker | `php artisan queue:work` (supervisor) | Email konfirmasi tidak terkirim |
| Database | MySQL 8 / MariaDB 10.4+, InnoDB | Penguncian baris & transaksi tidak bekerja |

Scheduler adalah komponen paling kritis setelah database: tanpa dia, setiap
pemesanan yang tidak dibayar akan menahan kamar secara permanen.

## 9.2 Variabel Environment Produksi

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com
APP_TIMEZONE=Asia/Makassar

DB_CONNECTION=mysql
DB_DATABASE=new_poppies_booking
DB_USERNAME=<pengguna khusus, bukan root>
DB_PASSWORD=<kata sandi kuat>

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

MAIL_MAILER=smtp
MAIL_HOST=<smtp host>
MAIL_PORT=587
MAIL_USERNAME=<user>
MAIL_PASSWORD=<pass>
MAIL_FROM_ADDRESS=reservasi@domain-anda.com

DOKU_ENVIRONMENT=production
DOKU_BASE_URL=https://api.doku.com
DOKU_CLIENT_ID=<dari dashboard DOKU>
DOKU_SECRET_KEY=<dari dashboard DOKU>
```

`APP_DEBUG=false` bersifat wajib: bila `true`, jejak galat dapat membocorkan
kredensial database dan kunci aplikasi.

## 9.3 Urutan Rilis

```bash
php artisan down --render="errors::503"
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

`php artisan queue:restart` penting agar worker memuat kode terbaru.

## 9.4 Rollback

1. `php artisan down`
2. Kembalikan kode ke tag sebelumnya.
3. Bila rilis menambah migrasi: `php artisan migrate:rollback --step=1`.
4. `php artisan optimize:clear`, lalu `php artisan up`.

**Jangan** menjalankan `php artisan migrate:fresh` di produksi — perintah itu
menghapus seluruh data pemesanan dan pembayaran.

## 9.5 Backup & Pemulihan

```bash
# Backup harian
mysqldump -u user -p --single-transaction new_poppies_booking \
  | gzip > /backup/npseng-$(date +%F).sql.gz

# Uji pemulihan (ke database terpisah!)
gunzip < /backup/npseng-2026-07-18.sql.gz | mysql -u user -p npseng_restore_test
```

Simpan minimal 7 salinan harian di media terpisah, dan uji restore secara berkala —
backup yang belum pernah diuji tidak dapat disebut backup.

Ikut cadangkan `storage/app/public` (foto kamar).

## 9.6 Pemantauan

| Yang dipantau | Cara |
|---------------|------|
| Galat aplikasi | `storage/logs/laravel.log` |
| Notifikasi gagal | `SELECT * FROM payment_events WHERE processing_status = 'failed'` |
| Signature tidak valid | `SELECT * FROM audit_logs WHERE action = 'payment.signature_invalid'` |
| Pembayaran perlu tinjauan | Kartu "Peninjauan Pembayaran" pada dashboard admin |
| Job antrean gagal | Tabel `failed_jobs` |
| Hold menumpuk | Jumlah booking `HELD` yang `held_until` sudah lewat harus ~0 |

## 9.6b Deploy Serverless (Vercel)

Aplikasi dapat berjalan di Vercel (runtime `vercel-php`, entri `api/index.php`),
namun ada beberapa hal khas serverless:

| Hal | Konsekuensi & solusi |
|-----|----------------------|
| **Prefiks `/api` dipesan Vercel** | Rute `/api/*` Laravel tidak pernah sampai ke router. Karena itu webhook DOKU dipindah ke **`/webhook/doku/notifications`** (rute web), dan health ke `/health`. |
| **`${APP_URL}` tidak diekspansi** | Di dashboard Vercel, isi URL penuh — `DOKU_NOTIFICATION_URL=https://<app>.vercel.app/webhook/doku/notifications`, `DOKU_CALLBACK_URL=https://<app>.vercel.app/payment/callback`, `APP_URL=https://<app>.vercel.app`. |
| **Tidak ada queue worker** | Deployment memakai `QUEUE_CONNECTION=sync`. Kegagalan email dicatat tetapi tidak menggagalkan konfirmasi pembayaran/webhook. SMTP tetap harus dikonfigurasi agar email benar-benar terkirim. |
| **Tidak ada scheduler tetap** | Vercel Cron harian memanggil `/cron/expire-booking-holds` dengan `CRON_SECRET`; pencarian dan pembuatan booking juga menyapu hold kedaluwarsa agar inventori tetap benar di antara jadwal cron. |
| **Filesystem `/tmp` sementara** | Deployment memakai `SESSION_DRIVER=cookie`; foto kamar disimpan di bucket publik Supabase Storage melalui `SUPABASE_URL`, `SUPABASE_SERVICE_ROLE_KEY`, dan `SUPABASE_STORAGE_BUCKET`. |
| **`APP_DEBUG=false`** | Wajib di produksi. |

Setelah mengubah rute/env, **redeploy** agar rute baru aktif, lalu daftarkan
Notification URL baru di dashboard DOKU dan uji: `POST` ke URL itu harus menjawab
**400** (bukan 404).

## 9.7 Verifikasi Pasca-Deploy

- [ ] Beranda, daftar kamar, dan pencarian dapat diakses
- [ ] Satu pemesanan uji dapat dibuat (status `HELD`)
- [ ] Hold uji kedaluwarsa setelah 30 menit dan inventaris kembali
- [ ] Pembayaran DOKU produksi berhasil dan pemesanan menjadi `CONFIRMED`
- [ ] Email konfirmasi diterima
- [ ] Login admin dan seluruh menu terbuka
- [ ] Laporan dan ekspor CSV berfungsi
- [ ] Halaman galat 404/500 tampil tanpa jejak galat
