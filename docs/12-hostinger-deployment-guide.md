# Facilities Sync Feature — Hostinger Deployment Guide

## Overview

Panduan deploy ke **Hostinger** untuk fitur facilities sync yang baru.

---

## Pre-Deployment Checklist

- [ ] Semua kode sudah di-push ke GitHub `main` branch
- [ ] Tests lokal sudah pass: `php artisan test --filter=PublicFacilitiesSyncTest`
- [ ] Assets sudah di-build: `npm run build`
- [ ] Code style sudah checked: `./vendor/bin/pint --test`

**Status Saat Ini:**
✅ Semua commit sudah di-push ke main branch:
- `c818d34` - HomeController update
- `418547f` - Blade view update
- `53b5fbb` - Test added
- `784016b` - Documentation

---

## Deployment Steps untuk Hostinger

### Step 1: SSH ke Server Hostinger

```bash
ssh username@your-hostinger-domain.com
# atau gunakan IP address
ssh username@xxx.xxx.xxx.xxx
```

Atau gunakan **File Manager** di Hostinger Panel jika lebih nyaman.

---

### Step 2: Navigate ke Project Root

```bash
cd /home/username/public_html
# atau folder tempat aplikasi laravel
cd /home/username/your-app-folder
```

---

### Step 3: Pull Latest Changes dari GitHub

```bash
git pull origin main
```

**Output yang diharapkan:**
```
Already up to date.
atau
Fast-forward
 app/Http/Controllers/HomeController.php           |  16 ++
 resources/views/public/home.blade.php             |  24 +-
 tests/Feature/Rooms/PublicFacilitiesSyncTest.php  | 100 ++
 docs/11-facilities-sync-implementation.md         | 250 ++
 4 files changed, ...
```

---

### Step 4: Install/Update Dependencies (Jika Ada)

```bash
# Update composer dependencies
composer install --no-dev --optimize-autoloader

# Update npm dependencies (jarang diperlukan)
npm install
```

---

### Step 5: Build Frontend Assets

```bash
npm run build
```

**Output yang diharapkan:**
```
✓ 123 modules transformed
  built in 2.34s
```

---

### Step 6: Clear Caches

```bash
# Clear config cache
php artisan config:clear

# Clear route cache
php artisan route:cache

# Clear view cache
php artisan view:clear

# Clear app cache
php artisan cache:clear
```

---

### Step 7: Verify Database

Pastikan tabel `amenities` dan data sudah ada:

```bash
php artisan tinker
>>> App\Models\Amenity::count()
# Seharusnya menampilkan jumlah > 0

>>> App\Models\Amenity::first()->toArray()
# Lihat struktur data
```

**Jika tidak ada amenities:**
```bash
# Re-run seeder untuk amenities
php artisan db:seed --class=RoomSeeder
```

---

### Step 8: Test Homepage

Buka browser dan akses homepage:
```
https://your-domain.com/
```

Scroll ke section "Fasilitas" dan verifikasi:
- ✅ Fasilitas tampil dari database (bukan hardcoded)
- ✅ Setiap fasilitas adalah name dari table `amenities`
- ✅ Responsive grid: 2 kolom mobile, 4 kolom desktop

---

### Step 9: Test Admin Panel

Login ke admin:
```
https://your-domain.com/admin
Email: admin@newpoppiessenggigi.test
Password: password (ganti dengan password real di production!)
```

Navigate to: **Admin → Fasilitas**

**Test Add:**
1. Klik "+ Fasilitas Baru"
2. Isi nama: "Test Facility Baru"
3. Isi kategori: "test"
4. Klik Simpan
5. Refresh homepage
6. Verifikasi "Test Facility Baru" muncul di section Fasilitas

**Test Edit:**
1. Di admin, klik Edit pada fasilitas yang baru dibuat
2. Ubah nama menjadi "Test Facility Updated"
3. Klik Simpan
4. Refresh homepage
5. Verifikasi nama berubah

**Test Delete:**
1. Di admin, klik Hapus pada fasilitas test
2. Confirm hapus
3. Refresh homepage
4. Verifikasi fasilitas hilang

---

## Troubleshooting untuk Hostinger

### Error 1: "Class 'App\Models\Amenity' not found"

**Penyebab:** Autoloader belum refresh
**Solusi:**
```bash
composer dump-autoload
php artisan config:clear
```

---

### Error 2: "SQLSTATE[42S02]: Table 'amenities' doesn't exist"

**Penyebab:** Database belum ter-setup atau migrations belum jalan
**Solusi:**
```bash
# Jalankan semua migrations
php artisan migrate

# Jika perlu fresh start (WARNING: menghapus data!)
php artisan migrate:fresh --seed
```

---

### Error 3: Homepage tapi fasilitas masih hardcoded

**Penyebab:** Hostinger cache belum di-clear atau file lama masih di-serve
**Solusi:**
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Clear browser cache (Ctrl+Shift+Delete atau Cmd+Shift+Delete)
# atau akses incognito/private window

# Tunggu 5 menit untuk CDN cache expire
```

---

### Error 4: 500 Internal Server Error

**Penyebab:** Kemungkinan banyak (syntax error, missing import, etc)
**Solusi:**
1. Cek error log:
   ```bash
   tail -50 storage/logs/laravel.log
   ```

2. Enable debug mode temporary (hanya untuk development!):
   ```bash
   # Edit .env
   APP_DEBUG=true
   php artisan config:clear
   ```

3. Reload page dan lihat error message
4. Disable debug setelah selesai testing

---

## Post-Deployment Monitoring

### Check 1: Log Files

```bash
# Lihat 100 baris terakhir
tail -100 storage/logs/laravel.log

# Monitor real-time
tail -f storage/logs/laravel.log
```

### Check 2: Database

```bash
# Verifikasi amenities still accessible
php artisan tinker
>>> App\Models\Amenity::orderBy('category')->orderBy('name')->get();
```

### Check 3: Homepage Response Time

```bash
# Test response time
curl -w "@curl-format.txt" -o /dev/null -s https://your-domain.com/
```

---

## Rollback Plan (Jika Ada Masalah)

Jika ada error dan perlu rollback:

```bash
# Reset ke commit sebelumnya
git revert 784016b3bd5baf5b7e62bdb8534ca296067df9b6

# atau reset langsung
git reset --hard HEAD~1

# Pull changes
git pull origin main

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

---

## Files Changed Summary

```
Modified:
  app/Http/Controllers/HomeController.php
  resources/views/public/home.blade.php

Added:
  tests/Feature/Rooms/PublicFacilitiesSyncTest.php
  docs/11-facilities-sync-implementation.md
```

---

## Verification Checklist Post-Deploy

- [ ] `git status` menunjukkan clean (semua files tracked)
- [ ] `php artisan tinker` bisa query `App\Models\Amenity`
- [ ] Homepage `https://your-domain.com/` load tanpa error
- [ ] Section Fasilitas menampilkan data dari database
- [ ] Empty state muncul jika tidak ada amenities
- [ ] Admin panel bisa add/edit/delete amenities
- [ ] Perubahan amenities langsung terlihat di homepage
- [ ] Responsive design works (test di mobile & desktop)
- [ ] Log files clean (tidak ada error)

---

## Quick Command Reference

```bash
# Akses project
cd /home/username/public_html

# Update dari GitHub
git pull origin main

# Install deps
composer install --no-dev --optimize-autoloader

# Build assets
npm run build

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear

# Test database
php artisan tinker

# View logs
tail -100 storage/logs/laravel.log

# Run migrations
php artisan migrate

# Rollback
git reset --hard HEAD~1
```

---

## Support

Jika ada masalah:

1. **Check logs:**
   ```bash
   tail -100 storage/logs/laravel.log
   ```

2. **Check database:**
   ```bash
   php artisan tinker
   >>> App\Models\Amenity::count()
   ```

3. **Check files:**
   ```bash
   git status
   git log --oneline -5
   ```

4. **Reference:**
   - Docs: `docs/11-facilities-sync-implementation.md`
   - GitHub: https://github.com/oyaji078/new-poppies-booking
   - Commits: c818d34, 418547f, 53b5fbb, 784016b

---

## Timeline

- ✅ **2026-09-08 22:39** - HomeController updated
- ✅ **2026-09-08 22:40** - Blade view updated
- ✅ **2026-09-08 22:41** - Tests added (7 scenarios)
- ✅ **2026-09-08 22:43** - Documentation completed
- ⏳ **NOW** - Deploy ke Hostinger

Semua siap untuk production! 🚀
