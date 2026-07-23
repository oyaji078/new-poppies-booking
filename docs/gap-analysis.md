# Gap Analysis — New Poppies Senggigi Booking System

_Phase 0 output. Baseline: empty repository (only the master prompt existed). Every requirement
below is therefore a build target; nothing pre-existing needs preservation._

Priorities: **Critical** (blocks core booking/payment/data integrity), **High** (core feature
users/admin depend on), **Medium** (important but not blocking), **Low** (polish / nice-to-have).

## Critical

| ID | Gap | Notes |
|----|-----|-------|
| C1 | No database schema | 27 tables from §26 of the prompt must be created with FKs, unique constraints, indexes. |
| C2 | No availability / daily-inventory engine | `room_type_inventories` + `AvailabilityService`; availability from inventory, never raw room count. |
| C3 | No double-booking prevention | DB transaction + `lockForUpdate()` on inventory rows, deterministic lock order, deadlock retry, no negative inventory. |
| C4 | No server-side pricing | `PricingService`, integer rupiah, nightly snapshots in `booking_item_nights`; never trust browser prices. |
| C5 | No booking hold / expiry lifecycle | 30-min hold, status enums, scheduler-driven expiry that releases held inventory. |
| C6 | No DOKU integration | Checkout creation, signature/digest, notification endpoint, idempotency via `payment_events`, amount/signature verification. |
| C7 | No payment-status state machine | Separate booking & payment statuses with validated transitions (PHP enums). |
| C8 | No security baseline | CSRF (except DOKU webhook), signature verification, form-request validation, authorization policies, secrets in env. |

## High

| ID | Gap | Notes |
|----|-----|-------|
| H1 | Authentication & authorization | Customer + admin; guest booking lookup requires booking code **and** email. |
| H2 | Room type / physical room / facility / image management | Admin CRUD + public display; room type vs physical room separation. |
| H3 | Room search + results UI | Date/guest validation, availability, per-night + total price, rooms-left. |
| H4 | Checkout flow (5 steps) | Search → select → guest details → review → payment → confirmation. |
| H5 | Promotions engine | %/fixed, code/auto, restrictions, quotas, expiry, server recalculation. |
| H6 | Payment attempts model | Multiple attempts, one active at a time, no double inventory reservation. |
| H7 | Cancellation + refund | Policy snapshot, refund records (never auto-mark refunded), no over-refund. |
| H8 | Check-in / check-out + room assignment | No overlapping physical-room assignment; audit on overrides. |
| H9 | Admin dashboard | DB-driven cards (reservations, check-ins/outs, pending payments, revenue, occupancy). |
| H10 | Reports + export | Reservations, payments, revenue, guests, occupancy, cancellations, refunds; CSV/Excel/PDF. |
| H11 | Audit log | Admin/security-relevant changes with before/after, IP, UA, redaction. |
| H12 | Scheduler + queue | Per-minute expiry; queued emails; retry-safe jobs. |

## Medium

| ID | Gap | Notes |
|----|-----|-------|
| M1 | FAQ chatbot | DB-backed, keyword match, safe fallback, stores unanswered questions. |
| M2 | Website content (pages) | Editable homepage/section content. |
| M3 | Inventory calendar (admin) | Block/reopen/price/bulk with guard rules + audit. |
| M4 | Email notifications | Booking/payment/cancellation/refund/reminder, queued. |
| M5 | System settings | Configurable tax, service charge, policies, hotel info. |
| M6 | Seeders | Admin, customers, 3 room types, rooms, facilities, prices, 90+ days inventory, FAQs, pages, sample bookings. |
| M7 | File uploads | MIME/size validation, random names, thumbnails, main-image selection, drag & drop. |

## Low

| ID | Gap | Notes |
|----|-----|-------|
| L1 | Thesis documentation set | `docs/01..11` with Mermaid diagrams + prototype iterations. |
| L2 | Print views | Booking confirmation, admin report print. |
| L3 | Accessibility & UI consistency pass | Loading/empty/error states everywhere. |
| L4 | Deployment docs | Local + production, queue/scheduler/backup, handover. |

## Cross-cutting risks identified

- **Security:** never trust price/amount/status/role/filename from the client; verify DOKU signature; webhook idempotency; login throttling; rate limiting; redact PII in logs/audit.
- **Database:** integer money; unique `bookings.code`; unique `room_type_id+inventory_date`; unique payment invoice/request IDs; unique `payment_events` key for idempotency; safe cascades (never hard-delete booking/payment history).
- **Booking/inventory:** deterministic lock order; deadlock retry; hold expiry must release inventory exactly once; late-payment recovery re-checks inventory.
- **DOKU:** field names must follow official docs, not guesses; browser callback is never proof of payment.
- **Testing:** concurrency test must use real InnoDB connections (not SQLite) to prove only one of two simultaneous bookings for the last room succeeds.
