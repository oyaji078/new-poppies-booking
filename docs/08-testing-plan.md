# 08 — Rencana & Hasil Pengujian

Kerangka uji: **PHPUnit** (bawaan Laravel). Database uji: `new_poppies_booking_test`
pada MariaDB/InnoDB — dipilih agar perilaku `lockForUpdate()` benar-benar teruji
(SQLite tidak dapat membuktikan penguncian baris).

Menjalankan:

```bash
php artisan test
php artisan test --filter=ConcurrentBookingTest
```

## 8.1 Hasil Terakhir

```
Tests:    229 passed (563 assertions)
Pint:     passed
Build:    npm run build → success
```

## 8.2 Uji Unit

| Berkas | Yang diuji |
|--------|-----------|
| `Unit/Support/StayPeriodTest` | Jumlah malam (checkout tidak dihitung), stay 1 malam, penolakan tanggal terbalik/sama |
| `Unit/Enums/BookingStatusTest` | Transisi sah, transisi terlarang (`CHECKED_OUT→HELD`, `CANCELLED→CHECKED_IN`, `HELD→CONFIRMED`), pemulihan terlambat, status terminal, penanda okupansi inventaris |
| `Unit/Enums/PaymentStatusTest` | Belum bayar tidak dapat direfund; deteksi terbayar |
| `Unit/Doku/DokuSignatureServiceTest` | Digest = base64(sha256(raw)); susunan komponen & urutannya; tanpa newline akhir; baris Digest dihilangkan untuk GET; prefix `HMACSHA256=`; verifikasi menerima yang sah, menolak digest diubah, menolak Request-Target berbeda, gagal tanpa secret |

## 8.3 Uji Fitur

| Berkas | Yang diuji |
|--------|-----------|
| `Feature/Auth/AuthenticationTest` | Login/registrasi, profil pelanggan dibuat, pengalihan admin, pelanggan ditolak dari admin, pembatasan 5 percobaan |
| `Feature/Settings/SettingServiceTest` | Nilai bertipe, nilai bawaan, pemisahan pengaturan publik/privat |
| `Feature/Audit/AuditLoggerTest` | Pencatatan audit, redaksi kunci sensitif |
| `Feature/Rooms/RoomDisplayTest` | Hanya tipe kamar terpublikasi tampil; draf 404 |
| `Feature/Rooms/RoomTypeManagerTest` | CRUD tipe kamar, validasi, toggle publikasi, proteksi rute |
| `Feature/Rooms/RoomImageServiceTest` | Foto pertama menjadi utama; hapus utama mempromosikan berikutnya; nama berkas acak |
| `Feature/Pricing/PricingServiceTest` | Harga dasar + pajak + layanan; akhir pekan; musiman; tamu tambahan; promo %/nominal; promo tidak valid diabaikan; **snapshot per malam berjumlah tepat sama dengan total** |
| `Feature/Pricing/PromotionServiceTest` | Promo kedaluwarsa, kuota habis, minimal malam, batasan tipe kamar, batas maksimal diskon, diskon ≤ subtotal |
| `Feature/Booking/AvailabilityServiceTest` | Minimum lintas malam; nol bila satu malam penuh; kamar pemeliharaan mengurangi ketersediaan; filter kapasitas |
| `Feature/Booking/InventoryServiceTest` | Baris dibuat dari inventaris bawaan; total tidak boleh < terkonfirmasi; blokir tidak melebihi sisa; pembaruan massal (malam checkout tidak ikut) |
| `Feature/Booking/BookingHoldTest` | Hold + snapshot + inventaris; penolakan saat penuh; penolakan bila satu malam penuh; tanggal lampau; kapasitas tamu; kedaluwarsa melepas inventaris; **kedaluwarsa dua kali tidak melepas ganda**; inventaris dapat dipakai lagi |
| `Feature/Booking/BookingFlowTest` | Halaman pencarian, validasi tanggal, checkout membuat hold, syarat wajib disetujui, akses pemesanan (kode+email), pemilik, penolakan pengguna lain |
| `Feature/Doku/DokuCheckoutServiceTest` | Permintaan bertanda tangan benar & **jumlah dari server**; HELD→PENDING_PAYMENT; attempt digunakan ulang; hold kedaluwarsa ditolak; rekonsiliasi total; **`line_items` berjumlah tepat sama dengan `order.amount`**; **halaman pembayaran tidak pernah hidup lebih lama daripada hold** |
| `Feature/Doku/DokuNotificationTest` | Konfirmasi + held→confirmed + email; **duplikat tanpa efek ganda**; signature tidak valid (401, nol perubahan); **tanpa header signature → 400 tanpa menulis apa pun**; Client-Id salah; jumlah tidak cocok → review; invoice tidak dikenal; gagal tetap dapat dibayar ulang; status tak dikenal → review; pembayaran terlambat dengan/tanpa inventaris |
| `Feature/Doku/DokuConsoleCommandsTest` | `doku:check` lolos pada konfigurasi sehat dan **gagal** pada notification URL localhost, host sandbox/produksi tertukar, dan jendela pembayaran melebihi hold; notifikasi simulasi **lolos verifier asli**; opsi signature palsu benar-benar ditolak |
| `Feature/Doku/DokuEnvironmentSwitchTest` | Hanya Super Admin membuka `/admin/doku` (customer/receptionist/manager/admin → 403, tamu → login); tautan sidebar tersembunyi dari admin biasa; perpindahan mode benar-benar memindahkan kredensial aktif; **setiap perpindahan teraudit lengkap dengan pelaku & alasan**; tanpa alasan ditolak; mode tanpa kredensial ditolak; layanan menolak non-super-admin walau UI ditembus; nilai mode tak dikenal jatuh ke bawaan |
| `Feature/Operations/CancellationAndRefundTest` | Pelepasan inventaris held/confirmed; terbayar → `REFUND_PENDING`; `CHECKED_IN`/`CHECKED_OUT` tidak dapat dibatalkan; alasan wajib; jendela 24 jam; belum bayar tidak dapat direfund; refund > terbayar ditolak; refund selesai hanya saat `SUCCEEDED`; refund sebagian; dua refund tidak melebihi total |
| `Feature/Operations/CheckInOutTest` | Penugasan kamar; **kamar sama tidak boleh tumpang tindih**; berurutan (checkout eksklusif) diizinkan; tipe kamar harus cocok; kamar pemeliharaan ditolak; jumlah kamar harus tepat; hanya `CONFIRMED` boleh check-in; check-in awal wajib alasan; check-out membebaskan kamar; no-show |
| `Feature/Reports/ReportServiceTest` | Rumus pendapatan bersih; kedaluwarsa/batal tidak dihitung; pembayaran gagal/kedaluwarsa tidak dihitung; refund tertunda tidak mengurangi; halaman & ekspor CSV |
| `Feature/Chatbot/ChatbotServiceTest` | Jawaban dari FAQ; FAQ nonaktif diabaikan; **harga dari database**; kamar belum terpublikasi tidak dikutip; tidak pernah mengklaim ketersediaan; menolak membuka data pemesanan; jam dari pengaturan; fallback tersimpan |
| `Feature/Security/SecurityGuardsTest` | Area admin tertutup; kode saja tidak membuka detail; pesan lookup tidak membocorkan keberadaan kode; webhook bebas CSRF namun dijaga signature; **harga dihitung ulang server**; kata sandi ter-hash; lookup dibatasi laju |
| `Feature/Admin/AdminPagesSmokeTest` | 14 halaman admin merender 200 — tidak ada rute mati |

## 8.4 Uji Konkurensi (Anti Pemesanan Ganda)

Berkas: `tests/Feature/Booking/ConcurrentBookingTest.php`

Uji ini **sengaja tidak** memakai `RefreshDatabase`, karena trait tersebut
membungkus setiap uji dalam transaksi sehingga justru menyembunyikan race yang
ingin dibuktikan. Sebagai gantinya data di-commit sungguhan, lalu beberapa
**proses sistem operasi terpisah** dijalankan bersamaan melalui `Process::pool()`
dan disinkronkan pada satu titik waktu (`--startAt`).

| Skenario | Harapan | Hasil |
|----------|---------|-------|
| 1 kamar tersisa, **4 proses** bersamaan | Tepat 1 berhasil, 3 ditolak rapi | ✅ Lulus |
| 3 kamar tersedia, **6 proses** bersamaan | Tepat 3 berhasil | ✅ Lulus |

Selain jumlah keberhasilan, uji juga memverifikasi pada **setiap malam menginap**:

- `held_inventory` bernilai tepat (1 dan 3),
- ketersediaan tidak pernah negatif,
- `held + confirmed + blocked` tidak pernah melebihi `total_inventory` (tidak oversold),
- hanya satu baris `bookings` yang tercipta pada skenario kamar terakhir.

## 8.5 Cakupan yang Belum Ada

| Area | Alasan / rencana |
|------|------------------|
| Transaksi DOKU sungguhan | Mode produksi dipilih; menunggu `APP_DEBUG=false`, domain https, dan pendaftaran webhook. Prosedur di `06-doku-payment-flow.md` §6.8 |
| Uji peramban (E2E) | Di luar lingkup; alur sudah dicakup uji fitur tingkat HTTP |
| Uji beban / performa | Belum dilakukan; disarankan sebelum musim ramai |
| Audit aksesibilitas otomatis | Baru pemeriksaan manual (label form, fokus, kontras) |
