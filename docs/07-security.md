# 07 — Keamanan

## 7.1 Ringkasan Kontrol

| Area | Kontrol | Implementasi |
|------|---------|--------------|
| CSRF | Aktif global; hanya webhook DOKU dikecualikan | `bootstrap/app.php` |
| XSS | Blade meng-escape otomatis (`{{ }}`) | Seluruh view |
| SQL Injection | Query builder & Eloquent (parameter binding) | Seluruh query |
| Otentikasi | Hash bcrypt, sesi aman | `User::casts()` → `hashed` |
| Pembatasan login | 5 percobaan per email+IP | `LoginRequest` |
| Otorisasi | Middleware `staff` untuk seluruh area admin | `EnsureUserIsStaff` |
| Hak istimewa | Middleware `superadmin` untuk pengalih mode DOKU | `EnsureUserIsSuperAdmin` |
| Rate limiting | Lookup 10/menit, webhook 120/menit | `routes/web.php`, `routes/api.php` |
| Webhook | Verifikasi signature HMAC-SHA256 + Client-Id | `DokuNotificationVerifier` |
| Idempotensi | Batasan unik pada `payment_events` | Migrasi `payment_events` |
| Rahasia | Hanya dari environment, dibaca via `config()` | `config/doku.php` |
| Redaksi data | Payload & audit disaring | `DokuPayloadRedactor`, `AuditLogger` |
| Unggahan berkas | Validasi MIME/ukuran, nama acak UUID | `RoomImageService` |
| Galat produksi | `APP_DEBUG=false` + halaman galat kustom | `resources/views/errors/` |

## 7.2 Yang Tidak Pernah Dipercaya

| Sumber | Penanganan |
|--------|------------|
| Harga dari peramban | Selalu dihitung ulang `PricingService` saat hold dan saat pembayaran |
| Jumlah pembayaran dari peramban | Yang dikirim ke DOKU adalah `bookings.total_amount` dari server |
| Status pembayaran dari URL | Callback hanya menampilkan status tersimpan; bukan bukti |
| Peran pengguna dari request | Peran dibaca dari kolom `users.role` di database |
| Nama berkas unggahan | Diganti UUID; ekstensi dinormalisasi |
| Callback peramban | Bukan bukti pembayaran; hanya notifikasi terverifikasi yang mengonfirmasi |

## 7.3 Kontrol Akses Data Pemesanan

Detail pemesanan hanya terbuka bila salah satu terpenuhi:

1. Pengguna login adalah pemilik (`bookings.user_id`), **atau**
2. Pengguna login berperan staf, **atau**
3. Pengunjung telah memverifikasi **kode pemesanan + email** pada sesi ini.

Kode pemesanan saja **tidak pernah** cukup. Pesan galat lookup dibuat identik untuk
kode yang ada maupun tidak ada, sehingga formulir tidak dapat dipakai memeriksa
keberadaan sebuah kode (diuji: `test_lookup_does_not_reveal_whether_a_booking_code_exists`).

Kode pemesanan sendiri dibuat acak (`NPS-YYYYMMDD-XXXXXX`, alfabet tanpa karakter
ambigu) dan tidak mengekspos id auto-increment.

## 7.3b Pemisahan Hak: Mode Pembayaran

Mode DOKU menentukan apakah tamu benar-benar dipungut uang. Salah arah pada
kedua sisi sama berbahayanya: sandbox pada situs produksi membuat pemesanan
terkonfirmasi tanpa pembayaran nyata; produksi pada lingkungan uji coba
memungut kartu sungguhan.

Peran `super_admin` memisahkan wewenang sensitif dari admin biasa. Khusus Super Admin:

| Area | Rute | Penjaga |
|------|------|---------|
| Mode & metode pembayaran DOKU | `/admin/doku` | `staff` + `superadmin` |
| Kelola pengguna & peran staf | `/admin/pengguna` | `staff` + `superadmin` |

Manajemen pengguna menegakkan invarian agar satu kesalahan klik tak mengunci semua
orang: tak bisa mengubah peran sendiri, tak bisa menonaktifkan diri sendiri, dan
Super Admin terakhir (yang aktif) tak bisa diturunkan/dinonaktifkan. Akun yang
dinonaktifkan (`users.is_active = false`) ditolak saat login **dan** kehilangan
akses admin di tengah sesi (middleware `staff`). Setiap perubahan tercatat audit
(`user.managed`).

Pengalihan mode DOKU dipisahkan dari peran admin biasa:

| Kontrol | Rincian |
|---------|---------|
| Peran | Hanya `super_admin`; `admin`, `manager`, `receptionist` mendapat **403** |
| Rute | `/admin/doku` bermiddleware `staff` **dan** `superadmin` |
| Komponen | Peran diperiksa ulang di `mount()` dan pada setiap aksi Livewire — satu aksi Livewire adalah permintaan HTTP tersendiri |
| Layanan | `DokuEnvironmentService::switchTo()` menolak pelaku non-super-admin walau UI ditembus |
| Menu | Tautan sidebar disembunyikan dari peran lain |
| Alasan | Wajib diisi (min. 5 karakter) dan tersimpan di audit log |
| Kredensial | Tetap di environment; yang tersimpan di basis data hanya **pilihan** mode |
| Pemberian peran | Hanya lewat CLI `php artisan user:make-superadmin` — sesi admin yang dibajak tidak dapat menaikkan haknya sendiri |
| Mode tanpa kredensial | Ditolak; tidak mungkin mematikan pembayaran karena salah pilih |
| Nilai tak dikenal | `active()` jatuh kembali ke mode bawaan, tidak pernah menggantung |

## 7.4 Keamanan Webhook

Endpoint `POST /api/payments/doku/notifications`:

1. Dikecualikan dari CSRF karena server-to-server — **bukan** karena keamanan dilonggarkan.
2. Menolak permintaan tanpa header wajib dengan **400**, sebelum menulis apa pun
   ke basis data. Permintaan tanpa `Request-Id` tidak memiliki kunci idempotensi,
   sehingga bila dicatat ia dapat dipakai membanjiri tabel `payment_events`.
3. Menolak `Client-Id` yang bukan milik kita (`hash_equals`).
4. Menghitung ulang signature atas **raw body** dan membandingkan dengan `hash_equals`
   (perbandingan waktu-konstan, aman dari timing attack).
5. Signature tidak valid → **401**, tanpa perubahan data, dicatat sebagai audit
   `payment.signature_invalid`.

Diuji: signature valid diterima; digest yang diubah ditolak; `Request-Target` berbeda
ditolak; tanpa secret key selalu gagal.

## 7.5 Redaksi Data Sensitif

Kunci berikut selalu diganti `[REDACTED]` sebelum disimpan:
`signature`, `digest`, `secret_key`, `token`, `token_id`, `card_number`, `cvv`,
`password`, `remember_token`, `id_card_number`.

Berlaku pada `payment_attempts.request_payload_redacted`,
`payment_attempts.response_payload_redacted`, `payment_events.payload_redacted`,
dan `audit_logs`.

## 7.6 Audit Log

Dicatat: login admin, perubahan kamar/harga/inventaris, perubahan pemesanan,
peninjauan pembayaran, konfirmasi pembayaran, signature tidak valid, pembatalan,
refund, check-in, check-out, no-show, perubahan pengaturan, override admin, dan
**perpindahan mode DOKU** (`doku.environment_switched`, lengkap dengan mode asal,
mode tujuan, pelaku, dan alasan).

Setiap catatan menyimpan pelaku, aksi, entitas, data sebelum/sesudah, alamat IP,
user agent, dan waktu.

Seluruh override admin (menyetujui/menolak pembayaran yang ditinjau, check-in lebih
awal, no-show) **mewajibkan alasan** yang ikut tersimpan.

## 7.7 Integritas Uang

- Uang disimpan sebagai bilangan bulat rupiah (`BIGINT`) — tidak ada galat pecahan.
- Sebelum membuat pembayaran, total pemesanan direkonsiliasi dengan jumlah snapshot
  per malam; ketidakcocokan menghentikan pembayaran dan dicatat sebagai kejadian kritis.
- Refund tidak pernah melebihi jumlah terbayar, diperiksa dua kali (saat pengajuan
  dan saat penyelesaian, di dalam kunci baris).

## 7.8 Sisa Risiko

| Risiko | Status | Mitigasi yang disarankan |
|--------|--------|--------------------------|
| Belum ada verifikasi email pengguna | Terbuka | Aktifkan `MustVerifyEmail` bila diperlukan |
| Belum ada reset kata sandi | Terbuka | Tambahkan alur reset bawaan Laravel |
| Belum ada 2FA untuk admin | Terbuka | Pertimbangkan untuk akun produksi |
| Uji sandbox DOKU belum dijalankan | Terbuka — kredensial di `.env` ditolak DOKU (`invalid_client_id`) | Salin ulang kredensial sandbox, lalu `php artisan doku:check --ping` (§6.8) |
| Rentang waktu `Request-Timestamp` belum divalidasi | Terbuka | Tolak notifikasi lebih tua dari mis. 5 menit (anti-replay tambahan; saat ini replay sudah dicegah oleh idempotensi) |
