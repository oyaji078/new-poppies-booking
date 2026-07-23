# PROJECT STATUS — New Poppies Senggigi Booking System

_Last updated: 2026-07-24 — all 10 phases (0–9) executed; DOKU config audit added (§5)._

## 1. Snapshot

| Item | Value |
|------|-------|
| Project | New Poppies Senggigi Booking System |
| Framework | Laravel **12.64.0** + Livewire **4.3** (see assumption A1) |
| PHP | 8.2.12 (XAMPP) |
| Database | **MariaDB 10.4.32**, InnoDB (see assumption A2) |
| Node / NPM | 24.13.1 / 11.8.0 |
| Frontend | Blade + Tailwind CSS v4 (Vite 7) + Alpine (via Livewire) |
| Timezone | Asia/Makassar (WITA) |
| UI language | Indonesian · Code/DB language: English |
| **Tests** | **231 passed (575 assertions)** |
| **Lint** | **Pint: passed** |
| **Build** | **`npm run build`: success** |

## 2. Key assumptions & deviations

**A1 — Laravel 12 instead of Laravel 13.** The only PHP runtime available is
**8.2.12**; Laravel 13 requires PHP 8.3+. Installing a new PHP into the existing
XAMPP stack would risk the user's other local projects. Laravel 12 is the latest
release running on PHP 8.2 and supports every required feature. Upgrading later is
a `composer.json` constraint bump.

**A2 — MariaDB 10.4 as the MySQL-compatible engine.** Required "MySQL 8.x/InnoDB";
environment provides MariaDB 10.4.32 — MySQL wire-compatible, InnoDB by default, and
fully supports `SELECT … FOR UPDATE`, which is what double-booking prevention needs.

**A3 — Money as integer rupiah** (`BIGINT`) everywhere; no floats.

**A4 — Seasonal adjustment picks one best-matching rule per date** (room-type
specific wins over global), not additive stacking.

**A5 — Room "maintenance" is a current-state flag**, not a date range; it caps
availability from today onward rather than per historical date.

**A6 — Refunds are recorded manually** (DOKU auto-refund API not used), and are
labelled "Manual" in the UI.

## 3. Phase status

| Phase | Title | Status |
|-------|-------|--------|
| 0 | Audit & scaffold | ✅ Done |
| 1 | Foundation | ✅ Done — enums, auth, authorization, layouts, settings, audit log |
| 2 | Rooms | ✅ Done — room types/rooms/amenities/images/pages, admin CRUD, public pages |
| 3 | Inventory & Pricing | ✅ Done — daily inventory, availability, pricing, promotions, calendar |
| 4 | Booking | ✅ Done — search, checkout, 30-min hold, snapshots, lookup, expiry |
| 5 | DOKU | ✅ **Sandbox verified live** — real checkout page created, booking confirmed; super-admin sandbox/production switch added. Production still blocked on `APP_DEBUG`/`APP_URL`/webhook (see §5) |
| 6 | Hotel Operations | ✅ Done — cancellation, refunds, check-in/assignment, check-out, no-show |
| 7 | Reports & Chatbot | ✅ Done — 8 reports + CSV/print, FAQ chatbot, admin FAQ queue |
| 8 | QA | ✅ Done — **multi-process concurrency proven**, security guards, Pint clean |
| 9 | Deployment & Docs | ✅ Done — README setup/deploy, thesis docs 01–11 |

## 4. Definition-of-Done checklist

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Public website works | ✅ | Live smoke: `/`, `/kamar`, `/cari`, `/pemesanan` → 200 |
| Admin dashboard works | ✅ | 14 admin pages render 200 (`AdminPagesSmokeTest`) |
| Responsive design | ✅ | Mobile nav, sticky mobile booking bar, responsive grids |
| Room management | ✅ | `RoomTypeManagerTest` |
| Inventory works | ✅ | `InventoryServiceTest` |
| Availability search | ✅ | `AvailabilityServiceTest`, live search returns seeded rooms |
| Pricing works | ✅ | `PricingServiceTest` incl. exact per-night reconciliation |
| Promotions work | ✅ | `PromotionServiceTest` |
| Booking hold works | ✅ | `BookingHoldTest` |
| Booking expiration works | ✅ | `BookingHoldTest` (release + idempotent re-run) |
| **Double booking prevented** | ✅ | **`ConcurrentBookingTest` — 4 parallel OS processes, 1 winner; 6 processes, 3 winners** |
| DOKU sandbox works end to end | ✅ **VERIFIED 2026-07-24** | `NPS-20260724-5F358V`: paid in the DOKU VA simulator → DOKU's **own** server-to-server notification (`Request-Id: VIRTUAL_ACCOUNT_BCA2226…`, not a simulate) arrived over the Cloudflare tunnel, passed signature verification, and drove `CONFIRMED`/`PAID`, held→confirmed, audit, queued email |
| DOKU notification verification | ✅ (tested w/ signed fixtures) | `DokuNotificationTest`, `DokuSignatureServiceTest` |
| Duplicate notification handling | ✅ | `DokuNotificationTest::duplicate…` |
| Booking confirmation | ✅ | `DokuNotificationTest` |
| Cancellation works | ✅ | `CancellationAndRefundTest` |
| Refund recording works | ✅ | `CancellationAndRefundTest` |
| Check-in / room assignment | ✅ | `CheckInOutTest` incl. overlap rejection |
| Check-out works | ✅ | `CheckInOutTest` |
| Chatbot works | ✅ | `ChatbotServiceTest` |
| Reports work | ✅ | `ReportServiceTest` + CSV export |
| Audit log works | ✅ | `AuditLoggerTest` + redaction |
| Authorization works | ✅ | `SecurityGuardsTest` |
| Migrations work | ✅ | All migrations run clean on MariaDB |
| Seeders work | ✅ | Settings, admin, rooms, inventory (120 days), FAQs, pages |
| Tests pass | ✅ | 231 passed |
| Frontend build passes | ✅ | Vite build success |
| No broken core route | ✅ | Smoke tests over public + admin routes |
| No dead buttons | ✅ | Sidebar links guarded by `Route::has()`; all CTAs wired |
| Documentation complete | ✅ | `docs/01`–`11`, README, this file |
| Local install documented | ✅ | README §3 |
| Production deploy documented | ✅ | README §6, `docs/09-deployment.md` |

## 5. DOKU status

**Sandbox is working end to end (2026-07-24).** Active mode was switched to
sandbox by the owner through `/admin/doku`. A real booking was created and DOKU
sandbox returned a live checkout page:

```
NPS-20260724-VX94UF   Rp 1.430.825
https://staging.doku.com/checkout-link-v2/50ae8b847acf4dd1888d0b130a927de8…
```

A correctly signed notification then drove it to `CONFIRMED` / `PAID` with
inventory moved held→confirmed.

**Fully closed 2026-07-24:** the app was exposed via a Cloudflare tunnel
(`cloudflared`), `APP_URL` pointed at it, and the notification URL registered in
the DOKU dashboard. Two bookings (`NPS-20260724-5F358V`, `NPS-20260724-C8YKNG`)
were paid through the DOKU **VA simulator**, and DOKU's **own** server-to-server
notification (`Request-Id: VIRTUAL_ACCOUNT_BCA…`) reached the webhook, verified,
and confirmed each booking with no manual step. Trusted proxies were enabled so
Laravel honours the tunnel's https.

Production remains configured and one super-admin click away, with the blockers
listed below still open.

Diagnosed 2026-07-24 (while `.env` still pointed at sandbox, `doku:check --ping`
returned `HTTP 400 invalid_client_id`): the same Client-Id was probed against both hosts with a
deliberately invalid signature (so nothing could be created). Sandbox answered
`invalid_client_id`; production answered `invalid_signature` — i.e. production
**recognises** the id. The credentials were **production credentials pointed at
the sandbox host**. A configuration problem, not a code problem.

**Decision (owner, 2026-07-24): run against DOKU production.** Payments are real
money; refunds must be processed manually in the DOKU dashboard.

**Sandbox credentials verified 2026-07-24.** A separate sandbox account was
registered and its credentials are now configured alongside production
(`DOKU_SANDBOX_*`). `doku:check --environment=sandbox --ping` returned a real
checkout URL (`https://staging.doku.com/checkout-link-v2/…`) — the first
successful DOKU API call in this project, proving the signature implementation
against the live gateway. Switching between the two is a super-admin action at
`/admin/doku` (see §6b), not a redeploy.

Still open before a production payment can complete:

| Blocker | Why |
|---|---|
| `APP_DEBUG=true` | An internet-reachable Laravel with debug on serves `DOKU_SECRET_KEY`, `APP_KEY` and the DB password on any stack trace. **Must be `false`.** |
| `APP_URL=http://localhost:8000` | DOKU cannot deliver notifications; without them no booking is ever confirmed. Needs the real https domain. |
| Secret Key unverified | `doku_key_…` does not match DOKU's usual `SK-…` shape. If `doku:check --ping` returns `invalid_signature`, re-copy it from dashboard.doku.com → Settings → API Keys. |
| Webhook not registered | dashboard.doku.com → Settings → Payment Settings → Webhook, pointing at `DOKU_NOTIFICATION_URL`. |

`php artisan doku:check` reports all four.

What IS proven: the signature/digest algorithm matches DOKU's published spec
(unit-tested), and every §19 payment rule works against correctly-signed
notification fixtures.

To finish (procedure in `docs/06-doku-payment-flow.md` §6.8):
1. `APP_DEBUG=false`, `APP_ENV=production`, `APP_URL=https://<domain sungguhan>`.
2. `php artisan config:clear && php artisan doku:check` → no errors.
3. `php artisan doku:check --ping` → "Kredensial DOKU valid." Note this creates a
   real (unpaid) Rp 10.000 checkout in the live merchant account.
4. Register the Notification URL in the DOKU dashboard.
5. Exercise the webhook end to end without waiting for a payment:
   `php artisan doku:simulate-notification {invoice}`.
6. Make one booking, pay it, then verify: `payment_events.signature_valid = 1`,
   booking `CONFIRMED`, inventory moved held→confirmed, confirmation email queued.
7. Re-send the same notification → expect `duplicate` with no side effects.

## 6. Fixes applied in the 2026-07-24 audit

| # | Defect | Why it mattered | Fix |
|---|--------|-----------------|-----|
| 1 | `order.line_items` did not add up to `order.amount` — per-room subtotals exclude tax, service and discount, and `price` was rounded per room | DOKU rejects a basket that does not reconcile with the amount (always for paylater channels), and the payment page showed figures that disagreed with the charge | Total allocated proportionally across items, remainder absorbed by the last line; `quantity` fixed at 1 so `price × quantity` is exact. `DokuCheckoutServiceTest` |
| 2 | `payment.payment_due_date` always sent the configured 30 minutes, even when the hold had 5 minutes left | The DOKU page outlived the inventory hold — a guest could pay for rooms already released and resold, landing in `PAYMENT_REVIEW` | Clamped to the remaining hold. `DokuCheckoutServiceTest` |
| 3 | A notification with no signature headers still wrote a `payment_events` row; `provider_request_id` was `NULL`, and MySQL treats NULLs as distinct, so the unique idempotency key did not bite | Unsigned junk (120/min through the throttle) could grow the table without limit, and a replay with no `Request-Id` was never detected as a duplicate | Missing headers → **400** before any write; when a request id is absent the payload hash becomes the idempotency key. `DokuNotificationTest`, `SecurityGuardsTest` |
| 4 | `GET /pembayaran/{booking}` created a real DOKU transaction and moved `HELD → PENDING_PAYMENT` | Browser prefetch, link scanners and antivirus URL checks could start payments nobody asked for | Route is now `POST` with a CSRF-protected form |
| 5 | `.env` block "Booking / hotel operational settings" (hold minutes, max nights, tax, service, check-in/out times) was read by nothing | Editing `BOOKING_HOLD_MINUTES` silently did nothing; these live in `system_settings` | `.env` / `.env.example` corrected; `doku:check` warns when the two disagree |
| 6 | Human-entered text was sent to DOKU verbatim | DOKU accepts only `a-z A-Z 0-9 . - / + , = _ : ' @ % ( )` and rejects the **entire** checkout — naming no field — for anything else. A guest called "José", or a room type pasted from Word with a typographic dash, could never pay. Found by running a real sandbox transaction. | Guest and room names transliterated to ASCII and filtered; accents survive as letters ("José" → "Jose"). `DokuCheckoutServiceTest` |
| 7 | DOKU's validation errors (`{"message":[...]}`) were not parsed | The real cause was replaced by "layanan pembayaran tidak merespons" in both the log and the guest's screen | All three DOKU error shapes now read. `DokuCheckoutServiceTest` |

## 6b. Super-admin DOKU mode switch (added 2026-07-24)

Sandbox and production are now configured **side by side**; which one is live is a
runtime choice, not a deploy.

| Aspect | Design |
|---|---|
| Credentials | Stay in `.env` as `DOKU_SANDBOX_*` / `DOKU_PRODUCTION_*`. Never written to the database — the documented "secrets from environment only" property is preserved. |
| Active mode | `system_settings.doku_environment`, applied on boot by `DokuServiceProvider` into the flat `config('doku.*')` keys every payment service already reads. No service needed changing. |
| Who may switch | New `super_admin` role only. `admin`, `manager`, `receptionist` get 403 and do not see the menu item. |
| Where | `/admin/doku` (`DokuEnvironmentSwitcher`), guarded by `staff` + `superadmin` middleware, re-checked in the component and again in the service. |
| Granting the role | CLI only: `php artisan user:make-superadmin <email>`. A hijacked admin session cannot escalate itself. |
| Accountability | Mandatory reason, audited as `doku.environment_switched` with actor, from, to; last 10 switches shown on the page. |
| Safety rails | Cannot switch to an environment whose credentials are missing; an unrecognised stored value falls back to the default instead of leaving the gateway pointing nowhere. |
| Shared rules | `DokuConfigurationCheck` now backs both `doku:check` and the admin screen, so CLI and UI can never disagree about what is broken. |

Covered by `DokuEnvironmentSwitchTest` (9 tests).

## 6c. Features added 2026-07-24 (invoice, refund automation, payment methods)

| Feature | What it does |
|---|---|
| **Branded one-page invoice** | `GET /pemesanan/{code}/invoice` — a self-contained A4 document (NP mark, hotel identity from settings, line items, subtotal/discount/tax/service/total, LUNAS stamp, cancellation-policy note, print/PDF button). Guarded by the same booking-access rule. `InvoiceTest`. |
| **Auto refund on cancellation** | Cancelling a paid booking computes the refund from the amount actually paid with a tiered fee (full inside the free window; `paid − cancellation_fee_percent%` after it, default 50%; nothing at/after check-in) and raises a `REQUESTED` refund record automatically. Payout stays a manual admin action (DOKU auto-refund not used). Fee is a setting. `CancellationFeeTest`. |
| **Payment method picker** | Super admin chooses which DOKU methods appear (QRIS, VA banks, e-wallet, cards, paylater) from `/admin/doku`; stored in settings, applied at request time, unknown values dropped. QRIS itself must be activated on the DOKU account. `DokuEnvironmentSwitchTest`. |
| **Realtime status + callback token** | The "waiting" pages poll `booking.status` and refresh on change; a signed callback token re-authorises a payer returning from DOKU when the session cookie did not survive the cross-host redirect. `PaymentStatusPollTest`, `PaymentCallbackTokenTest`. |

**Admin vs super-admin — disjoint roles (2026-07-24).** The two sidebars are now
deliberately separate: **Admin** owns daily operations (reservations, front desk,
rooms, inventory, promotions, reports, …); **Super Admin** owns system/money
configuration only (Pengaturan Sistem, Mode Pembayaran DOKU, Kelola Pengguna) plus
the shared Dashboard. Super Admin no longer carries the operational menus. The
config routes are super-admin-gated; role granting is also available via CLI
(`user:make-superadmin`). `MenuSeparationTest`, plus the per-page guard tests.
`AdminUserSeeder` now seeds both a super admin and an admin so a fresh install can
do both immediately.

Demo accounts (`DemoUsersSeeder`, password `password`, **dev only**):
`superadmin@`, `admin@`, `pelanggan@newpoppiessenggigi.test`.

**Payment methods.** `DOKU_PAYMENT_METHODS=QRIS` — a single method sends the guest
straight to a full-page QR (largest QRIS presentation; DOKU's hosted page cannot be
restyled by us). Empty = show every active method; a comma list curates them.
Changeable by a super admin at `/admin/doku`.

**System settings** (`SettingsManager`, `/admin/pengaturan`): super-admin-only
editor for hotel identity, tax/service percentages, hold duration, max nights, and
cancellation policy (free-window hours + fee %). Each field is typed and validated;
hold shorter than the DOKU payment window is rejected; every save is audited
(`settings.change`). `SettingsManagerTest` (5).

**Customer booking history** (`AccountController`, `/pemesanan-saya`): a signed-in
guest's own reservations — production-style cards with status badge, dates, room,
total, and contextual actions (Detail, Invoice when confirmed, Bayar Sekarang when
payable). Shows only bookings owned by the account; "Pemesanan Saya" appears in the
public nav for signed-in customers. `BookingHistoryTest` (4).

**User management** (`UserManager`, `/admin/pengguna`): super-admin-only staff
account CRUD — create accounts, assign roles, reset passwords, activate/deactivate.
New `users.is_active` column (migration `2026_07_24_000001`) is enforced at login
and in the `staff` middleware, so a disabled account is locked out at the door and
mid-session. Invariants prevent self-lockout: cannot change your own role, cannot
deactivate yourself, cannot demote/deactivate the last active super admin. Audited
as `user.managed`. Covered by `UserManagerTest` (9) and `InactiveUserTest` (3).

## 6d. Decision log

- **DOKU stays non-SNAP** (owner, 2026-07-24). The non-SNAP Checkout integration
  is proven end to end; the earlier notification failure was a misplaced
  dashboard URL (SNAP settings vs the VA channel), not a need for SNAP. A SNAP
  migration would be a full rewrite of a working, tested payment layer.
- **Refund automation = compute + record, manual payout** (owner, 2026-07-24).
- **Cancellation fee = percentage, default 50%, configurable** (owner, 2026-07-24).

## 7. Other known limitations

| Item | Note |
|------|------|
| Password reset / email verification | Not implemented (login, registration, throttling are) |
| Seeded room photos | None — GD extension absent; UI degrades to gradient placeholders. Admin uploads real photos. |
| PDF export | CSV + print view implemented; PDF needs a package (e.g. dompdf) |
| Browser (E2E) tests | Not implemented; flows covered at HTTP/component level |
| Load testing | Not performed |
| `_scaffold/` empty dir | Harmless leftover from scaffolding; could not be removed (deletion blocked in this session) |

## 8. Operational must-dos

1. **Cron scheduler is mandatory** — without `* * * * * php artisan schedule:run`,
   expired holds never release their inventory and rooms stay locked forever.
2. **Queue worker required** for confirmation emails.
3. `APP_DEBUG=false` in production.
4. Change/remove the demo admin (`admin@newpoppiessenggigi.test` / `password`) —
   it is seeded as **super admin**, so leaving it in place hands a stranger the
   power to switch the payment gateway. Promote a real account first:
   `php artisan user:make-superadmin <email>`.

## 9. Quick commands

```bash
composer install && npm install
php artisan migrate --seed
php artisan storage:link && npm run build
php artisan test
php artisan test --filter=ConcurrentBookingTest   # double-booking proof
./vendor/bin/pint --test
php artisan serve & php artisan queue:work & php artisan schedule:work

php artisan doku:check --ping                     # DOKU config + credential test
php artisan doku:simulate-notification {invoice}  # signed webhook, no ngrok needed
```
