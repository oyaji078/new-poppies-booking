# 04 — Rancangan Basis Data (ERD)

Mesin: **InnoDB**, charset `utf8mb4`. Seluruh nilai uang berupa `BIGINT` rupiah.

## 4.1 ERD

```mermaid
erDiagram
    users ||--o| customer_profiles : memiliki
    users ||--o{ bookings : membuat
    users ||--o{ audit_logs : melakukan

    room_types ||--o{ rooms : terdiri_atas
    room_types ||--o{ room_images : memiliki
    room_types ||--o{ room_type_inventories : dijadwalkan
    room_types ||--o{ rate_plans : bertarif
    room_types ||--o{ seasonal_rates : disesuaikan
    room_types }o--o{ amenities : memiliki
    room_types }o--o{ promotions : berlaku_untuk
    room_types ||--o{ booking_items : dipesan

    bookings ||--o{ booking_items : berisi
    bookings ||--o{ booking_guests : mencatat
    bookings ||--o{ payment_attempts : dibayar_dengan
    bookings ||--o{ cancellation_requests : dibatalkan
    bookings ||--o{ refunds : direfund
    bookings }o--o| promotions : memakai

    booking_items ||--o{ booking_item_nights : snapshot_harga
    booking_items ||--o{ room_assignments : ditugaskan
    rooms ||--o{ room_assignments : menampung

    payment_attempts ||--o{ payment_events : menghasilkan
    payment_attempts ||--o{ refunds : sumber_dana

    faqs ||--o{ chatbot_messages : menjawab
    chatbot_sessions ||--o{ chatbot_messages : berisi

    users {
        bigint id PK
        string name
        string email UK
        string role "admin|customer|receptionist|manager"
        string password
        timestamp last_login_at
    }

    room_types {
        bigint id PK
        string name
        string slug UK
        int adult_capacity
        int max_guests
        bigint base_price "rupiah"
        int default_inventory
        bool is_published
    }

    rooms {
        bigint id PK
        bigint room_type_id FK
        string room_number UK
        string floor
        bool is_active
        bool under_maintenance
    }

    room_type_inventories {
        bigint id PK
        bigint room_type_id FK
        date inventory_date
        int total_inventory
        int blocked_inventory
        int held_inventory
        int confirmed_inventory
    }

    bookings {
        bigint id PK
        string code UK "NPS-YYYYMMDD-XXXXXX"
        bigint user_id FK "null = tamu"
        string customer_email
        date check_in_date
        date check_out_date
        int nights
        int rooms
        string status
        string payment_status
        bigint subtotal_amount
        bigint discount_amount
        bigint tax_amount
        bigint service_amount
        bigint total_amount
        timestamp held_until
        json cancellation_policy
    }

    booking_items {
        bigint id PK
        bigint booking_id FK
        bigint room_type_id FK
        string room_type_name "snapshot"
        int rooms
    }

    booking_item_nights {
        bigint id PK
        bigint booking_item_id FK
        date stay_date
        bigint base_amount
        bigint adjustment_amount
        bigint discount_amount
        bigint tax_amount
        bigint final_amount
        json pricing_metadata
    }

    payment_attempts {
        bigint id PK
        bigint booking_id FK
        string invoice_number UK
        string request_id UK
        bigint amount
        string status
        timestamp expires_at
        timestamp paid_at
        json request_payload_redacted
        json response_payload_redacted
    }

    payment_events {
        bigint id PK
        bigint payment_attempt_id FK
        string provider_request_id
        string payload_hash
        bool signature_valid
        string processing_status
        json payload_redacted
    }

    room_assignments {
        bigint id PK
        bigint booking_item_id FK
        bigint room_id FK
        date stay_from
        date stay_to "eksklusif"
        timestamp check_in_at
        timestamp check_out_at
    }

    refunds {
        bigint id PK
        bigint booking_id FK
        bigint payment_attempt_id FK
        bigint amount
        string status
        bool is_manual
        timestamp processed_at
    }
```

## 4.2 Batasan Unik yang Menegakkan Aturan Bisnis

| Tabel | Batasan | Menjamin |
|-------|---------|----------|
| `room_type_inventories` | UNIQUE(`room_type_id`, `inventory_date`) | Satu baris inventaris per tipe per tanggal |
| `bookings` | UNIQUE(`code`) | Kode pemesanan tidak pernah bentrok |
| `rate_plans` | UNIQUE(`room_type_id`, `rate_date`) | Satu harga khusus per tanggal |
| `booking_item_nights` | UNIQUE(`booking_item_id`, `stay_date`) | Satu snapshot per malam |
| `payment_attempts` | UNIQUE(`invoice_number`), UNIQUE(`request_id`) | Invoice & request tidak terduplikasi |
| `payment_events` | UNIQUE(`provider`, `provider_request_id`) | **Idempotensi notifikasi** |
| `payment_events` | UNIQUE(`payment_attempt_id`, `payload_hash`) | Cadangan idempotensi bila request id dipakai ulang |
| `rooms` | UNIQUE(`room_number`) | Nomor kamar tunggal |
| `promotions` | UNIQUE(`code`) | Kode promo tunggal |

## 4.3 Indeks Penting

- `bookings`: `code`, `customer_email`, `status`, `payment_status`,
  `check_in_date`, `check_out_date`, `held_until`, gabungan (`status`,`check_in_date`)
- `room_type_inventories`: (`room_type_id`,`inventory_date`)
- `payment_attempts`: `status`, (`booking_id`,`status`)
- `payment_events`: `processing_status`
- `room_assignments`: (`room_id`,`stay_from`,`stay_to`) — untuk deteksi tumpang tindih
- `audit_logs`: `action`, (`entity_type`,`entity_id`), `created_at`

## 4.4 Aturan Penghapusan

| Relasi | Aturan | Alasan |
|--------|--------|--------|
| `booking_items` → `room_types` | `RESTRICT` | Tipe kamar yang pernah dipesan tidak boleh hilang |
| `room_assignments` → `rooms` | `RESTRICT` | Riwayat penugasan kamar dipertahankan |
| `bookings` → `users` | `SET NULL` | Menghapus akun tidak menghapus riwayat pemesanan |
| `booking_items` → `bookings` | `CASCADE` | Item mengikuti induknya |
| `payment_events` → `payment_attempts` | `SET NULL` | Jejak audit pembayaran tetap ada |

Riwayat pemesanan dan pembayaran **tidak pernah dihapus permanen** oleh proses bisnis;
pembatalan hanya mengubah status.
