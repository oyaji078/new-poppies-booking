# Implementation Plan — New Poppies Senggigi Booking System

_Phase 0 output. Executes the development phases from §40 of the master prompt, in order._

## Guiding principles

- Thin controllers/Livewire; business logic in services/actions (§6).
- English for code/DB; Indonesian for UI.
- Money = integer rupiah. Availability & pricing computed server-side.
- All inventory/booking mutations inside DB transactions with `lockForUpdate()`.
- PHP enums for booking/payment status with validated transitions.
- Tests + migration + build run at the end of each phase (subject to command permissions).

## Phase 1 — Foundation
- `composer require livewire/livewire`; add Alpine.js to `resources/js/app.js`.
- Enums: `BookingStatus`, `PaymentStatus`, `PaymentAttemptStatus`, `RefundStatus`, `PromotionType`, `UserRole`, `AuditAction`.
- `users` extended with `role`; `customer_profiles`; `system_settings`; `audit_logs`.
- `SettingService`, `AuditLogger` service + `Auditable` trait/observer.
- Auth (Breeze-style or hand-rolled Livewire): login/register/logout; admin guard via role + policy/gate.
- Public layout (navbar/footer/chatbot slot) and admin layout (sidebar/navbar/breadcrumb).
- Base tests: settings, audit log write, auth, role gate.

## Phase 2 — Rooms
- Migrations: `room_types`, `rooms`, `amenities`, `amenity_room_type`, `room_images`, `pages`.
- Models + relationships + policies.
- Admin CRUD (Livewire) for room types, rooms, amenities, images (upload: MIME/size, random name, main image, sorting).
- Public: homepage, room listing, room detail (gallery, facilities, policies, sticky booking card).
- `WebsiteContentService` for editable `pages`.
- Tests: room type CRUD, image upload validation, slug uniqueness, publication scope.

## Phase 3 — Inventory & Pricing
- Migrations: `room_type_inventories` (unique `room_type_id+inventory_date`), `rate_plans`, `seasonal_rates`, `promotions`, `promotion_room_type`.
- `AvailabilityService` (per-night availability from inventory, maintenance reduces supply).
- `PricingService` (base + weekend + seasonal + special + extra-guest → subtotal → promo → tax/service → grand total), integer rupiah.
- `PromotionService` (validate %/fixed, code/auto, restrictions, quota, expiry, min nights/spend).
- Admin inventory calendar (block/reopen/price/bulk) with guard rules + audit.
- Tests: nights count, weekend/seasonal pricing, promo %/fixed/expired, availability multi-night, capacity.

## Phase 4 — Booking
- Migrations: `bookings`, `booking_guests`, `booking_items`, `booking_item_nights`.
- Booking code generator (`NPS-YYYYMMDD-XXXXXX`, unique index, not sequential).
- `BookingInventoryService` (lock + check + increment held), `BookingService` (create hold, items, nightly snapshots) in one transaction with deadlock retry.
- `BookingExpirationService` + scheduled command (every minute) releasing held inventory.
- Livewire checkout wizard (5 steps) with server-authoritative totals + countdown.
- Guest booking lookup (code + email).
- Tests: hold creation, inventory decrement, invalid dates, expiry release, guest-capacity rejection.

## Phase 5 — DOKU
- `config/doku.php`; `DokuSignatureService`, `DokuCheckoutService`, `DokuNotificationVerifier`, `DokuPaymentMapper`, `DokuNotificationService`.
- Migrations: `payment_attempts`, `payment_events`.
- Checkout creation from backend (recalculate total, unique invoice/request id, redacted payloads).
- `POST /webhook/doku/notifications` (CSRF-exempt): raw body, headers, signature, client id, invoice, amount, currency, duplicate detection, transactional confirm.
- Callback page ("Pembayaran sedang diperiksa" when no notification yet).
- Payment rules: success, failure, expired hold, late payment (re-check inventory), amount mismatch → review, invalid signature → security log.
- Confirmation email (queued). Tests: digest, signature, mapping, notification success, duplicate, invalid signature, amount mismatch, late payment ±inventory.

## Phase 6 — Hotel Operations
- Migrations: `room_assignments`, `cancellation_requests`, `refunds`.
- `CancellationService` (policy snapshot, status rules), `RefundService` (records, no over-refund, manual/pending labelling).
- `CheckInService` (assign physical room; no overlap; guest identity; CHECKED_IN), `CheckOutService` (close assignment; CHECKED_OUT; extra charges).
- No-show handling; guest records view.
- Tests: cancel (held/confirmed), refund cap, check-in overlap conflict, check-out.

## Phase 7 — Reports & Chatbot
- Migrations: `faqs`, `chatbot_sessions`, `chatbot_messages`.
- `ReportService` (reservations/payments/revenue/guests/occupancy/cancellations/refunds; net = gross − discount − refund; exclude failed/expired).
- Export (CSV/Excel via maatwebsite or native; PDF via dompdf if available), print views.
- `ChatbotService` (keyword match, safe fallback, store unanswered), floating widget, admin FAQ CRUD.
- Tests: revenue calc excludes failed/expired; chatbot match + fallback.

## Phase 8 — QA
- Complete unit + feature test coverage per §36.
- Concurrency test: last room, two simultaneous holds, exactly one succeeds, inventory ≥ 0 (real InnoDB connections).
- Security review (CSRF, authz, throttling, rate limiting, redaction), performance (eager loading / N+1), accessibility, UI consistency, error handling.

## Phase 9 — Deployment
- Finalize `.env.example`; document local + production setup, queue worker, scheduler, backup.
- Seeders + demo account; thesis docs `01..11` with Mermaid; final test report; handover guide.

## Execution notes
- Run after each phase (permissions permitting): `php artisan migrate`, `php artisan db:seed`, `php artisan test`, `npm run build`.
- Never `migrate:fresh` on a non-disposable DB. The two `new_poppies_booking*` DBs here are disposable/local.
