# 05 — Alur Pemesanan

## 5.1 Diagram Aktivitas

```mermaid
flowchart TD
    A[Tamu membuka situs] --> B[Isi tanggal & jumlah tamu]
    B --> C{Validasi tanggal}
    C -->|Check-in lampau / check-out <= check-in / > 30 malam| C1[Tampilkan pesan galat] --> B
    C -->|Valid| D[AvailabilityService: cek tiap malam]
    D --> E{Ada tipe kamar tersedia?}
    E -->|Tidak| E1[Empty state: sarankan ubah tanggal] --> B
    E -->|Ya| F[Tampilkan hasil + harga total dari server]
    F --> G[Tamu memilih kamar]
    G --> H[Isi data tamu + setujui syarat]
    H --> I[Tinjau rincian harga per malam]
    I --> J{Terapkan kode promo?}
    J -->|Ya| J1[PromotionService validasi] --> I
    J -->|Tidak| K[Konfirmasi]
    K --> L[[TRANSAKSI: kunci inventaris tiap malam]]
    L --> M{Kapasitas cukup di dalam kunci?}
    M -->|Tidak| M1[Tolak: kamar sudah habis] --> B
    M -->|Ya| N[held_inventory += jumlah kamar]
    N --> O[Buat booking + item + snapshot harga per malam]
    O --> P[[COMMIT]]
    P --> Q[Status HELD, held_until = +30 menit]
    Q --> R[Buat pembayaran DOKU]
    R --> S[Status PENDING_PAYMENT, alihkan ke halaman DOKU]
    S --> T{Notifikasi DOKU sah diterima?}
    T -->|Ya, jumlah cocok| U[CONFIRMED + held→confirmed + email]
    T -->|Jumlah tidak cocok| V[PAYMENT_REVIEW]
    T -->|Tidak ada, 30 menit lewat| W[Scheduler: EXPIRED + lepas inventaris]
    U --> X([Selesai])
    V --> X
    W --> X
```

## 5.2 Diagram Sekuens — Pembuatan Hold

```mermaid
sequenceDiagram
    autonumber
    actor T as Tamu
    participant UI as CheckoutWizard (Livewire)
    participant BS as BookingService
    participant PS as PricingService
    participant IS as BookingInventoryService
    participant DB as MySQL (InnoDB)

    T->>UI: Konfirmasi pemesanan
    UI->>BS: createHold(roomType, stay, rooms, tamu)
    BS->>BS: assertStayIsBookable() (tanggal, kapasitas, maks malam)

    BS->>DB: BEGIN
    BS->>IS: lockRows(roomType, stay)
    IS->>DB: INSERT IGNORE baris inventaris yang belum ada
    IS->>DB: SELECT ... WHERE date IN (...) ORDER BY inventory_date FOR UPDATE
    DB-->>IS: baris terkunci (urut menaik)

    BS->>IS: hasCapacity(rows, stay, rooms, batasKamarFisik)
    alt Ada malam yang tidak cukup
        IS-->>BS: false
        BS->>DB: ROLLBACK
        BS-->>UI: BookingException("kamar tidak tersedia")
        UI-->>T: Pesan galat + saran ubah tanggal
    else Cukup
        IS-->>BS: true
        BS->>PS: quote(...) hitung ulang harga di server
        PS-->>BS: PriceQuote (subtotal, diskon, pajak, layanan, total, per malam)
        BS->>IS: increaseHeld(rows, rooms)
        IS->>DB: UPDATE held_inventory = held_inventory + n
        BS->>DB: INSERT bookings (code unik, status HELD, held_until +30m)
        BS->>DB: INSERT booking_items
        BS->>DB: INSERT booking_item_nights (snapshot harga)
        BS->>DB: INSERT booking_guests
        BS->>DB: COMMIT
        BS-->>UI: Booking
        UI-->>T: Alihkan ke halaman status / pembayaran
    end
```

## 5.3 Skenario Konkurensi

```mermaid
sequenceDiagram
    autonumber
    participant A as Proses A
    participant B as Proses B
    participant DB as InnoDB

    Note over A,B: Tersisa 1 kamar untuk tanggal yang sama

    A->>DB: BEGIN; SELECT ... FOR UPDATE (10, 11 Agu)
    DB-->>A: baris terkunci
    B->>DB: BEGIN; SELECT ... FOR UPDATE (10, 11 Agu)
    Note over B,DB: B MENUNGGU — baris dikunci A

    A->>DB: tersedia = 1 >= 1 → held += 1
    A->>DB: COMMIT
    DB-->>B: kunci dilepas, B membaca nilai TERBARU
    B->>DB: tersedia = 0 → tolak
    B->>DB: ROLLBACK

    Note over A,B: Tepat satu pemesanan berhasil; inventaris tidak pernah negatif
```

Terbukti oleh `tests/Feature/Booking/ConcurrentBookingTest.php`, yang menjalankan
4 dan 6 proses sistem operasi **terpisah** secara bersamaan.

## 5.4 Kedaluwarsa Hold

```mermaid
sequenceDiagram
    autonumber
    participant CR as Cron (tiap menit)
    participant CMD as bookings:expire-holds
    participant ES as BookingExpirationService
    participant DB as MySQL

    CR->>CMD: php artisan schedule:run
    CMD->>ES: expireDueHolds()
    ES->>DB: SELECT bookings HELD/PENDING_PAYMENT dengan held_until < now()
    loop tiap pemesanan
        ES->>DB: BEGIN; SELECT booking FOR UPDATE
        alt Sudah ditangani proses lain
            ES->>DB: ROLLBACK (lewati)
        else Masih kedaluwarsa
            ES->>DB: kunci baris inventaris; held_inventory -= n
            ES->>DB: status = EXPIRED, payment_status = EXPIRED
            ES->>DB: attempt PENDING → EXPIRED
            ES->>DB: COMMIT + audit log
        end
    end
```

Pemeriksaan ulang di dalam kunci membuat perintah ini **aman dijalankan berulang**:
menjalankannya dua kali tidak pernah melepas inventaris dua kali.

## 5.5 Aturan Validasi Pencarian

| Aturan | Pesan |
|--------|-------|
| Check-in ≥ hari ini | "Tanggal check-in tidak boleh sebelum hari ini." |
| Check-out > check-in | "Tanggal check-out harus setelah tanggal check-in." |
| Maksimal 30 malam | "Lama menginap maksimal 30 malam." |
| Jumlah kamar ≥ 1 | "Jumlah kamar minimal 1." |
| Tamu ≤ kapasitas tipe | "Jumlah tamu melebihi kapasitas tipe kamar yang dipilih." |
