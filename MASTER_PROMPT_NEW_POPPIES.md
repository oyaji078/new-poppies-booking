# MASTER PROMPT — NEW POPPIES SENGGIGI BOOKING SYSTEM

## 1. ROLE

Act as a:

- Senior Laravel Developer
- Software Architect
- MySQL Database Engineer
- UI/UX Developer
- QA Engineer
- Security Reviewer

Your job is to inspect the existing project, fix it, and complete it into a working hotel booking website.

Do not only create mockups, static pages, or documentation.

Build a functional system with:

- Frontend
- Backend
- MySQL database
- Booking logic
- Room availability
- DOKU payment
- Admin dashboard
- Reports
- Chatbot
- Testing
- Deployment documentation

---

## 2. PROJECT

Project name:

**New Poppies Senggigi Booking System**

Business location:

**New Poppies Senggigi, Senggigi, Lombok Barat, Nusa Tenggara Barat**

Timezone:

**Asia/Makassar**

Main objectives:

1. Promote New Poppies Senggigi online.
2. Allow customers to search available rooms.
3. Allow customers to book rooms online.
4. Accept online payments through DOKU.
5. Prevent double booking.
6. Help admin manage rooms, reservations, guests, payments, and reports.
7. Provide a simple FAQ chatbot.
8. Produce a system suitable for a university thesis using the Prototype method.

---

## 3. REQUIRED STACK

Use:

- PHP 8.3 or compatible version
- Laravel 13
- Blade
- Livewire
- Alpine.js
- Tailwind CSS
- MySQL 8.x
- InnoDB
- Vite
- Laravel Queue
- Laravel Scheduler
- Laravel Mail and Notifications
- Pest or PHPUnit
- DOKU Checkout
- SMTP email

Architecture:

**Modular Monolith**

Do not replace MySQL.

Do not convert the project into microservices.

Do not use React, Vue, Next.js, or another SPA framework unless the existing project already depends on it and removing it would damage the project.

Use English for:

- Class names
- Method names
- Database tables
- Database columns
- Variables
- Internal code

Use clear Indonesian for user interface text.

---

## 4. WORKING RULES

Before changing code:

1. Read the entire project structure.
2. Check framework versions.
3. Read routes, migrations, models, controllers, services, views, Livewire components, tests, and configuration.
4. Identify existing working features.
5. Identify errors and missing features.
6. Do not rebuild the project from zero unless the current project is unusable.
7. Do not delete working features without a valid technical reason.
8. Do not invent files, functions, database tables, or APIs that do not exist.
9. Do not hardcode passwords, DOKU credentials, prices, room availability, or payment status.
10. Do not leave dead buttons, empty pages, fake data, `TODO`, or `FIXME` in core features.
11. Do not say a feature works before testing it.
12. Fix errors before continuing to the next phase.

After each phase:

- List created files.
- List modified files.
- Run migrations.
- Run tests.
- Run frontend build.
- Report errors.
- Fix critical errors.
- Update `PROJECT_STATUS.md`.

---

## 5. USERS

The system has two main actors:

### Customer

Customer can:

- View hotel information.
- View room types.
- View room photos and facilities.
- Search rooms by date.
- Check room availability.
- Book rooms.
- Pay through DOKU.
- View booking status.
- Print booking confirmation.
- Cancel a booking according to policy.
- Submit special requests.
- Use the FAQ chatbot.
- Manage profile when logged in.

Customers may search rooms without logging in.

At checkout, customer can:

- Log in.
- Register.
- Continue as guest.

Guest booking access must require:

- Booking code
- Customer email

Never show booking details using only a booking code.

### Admin

Admin can:

- Log in.
- Manage room types.
- Manage physical rooms.
- Manage facilities.
- Manage room images.
- Manage daily inventory.
- Manage room prices.
- Manage promotions.
- Manage reservations.
- View guests.
- View payments.
- Review payment problems.
- Process cancellations.
- Record refunds.
- Assign physical rooms.
- Process check-in.
- Process check-out.
- Manage website content.
- Manage chatbot FAQs.
- View reports.
- Export reports.
- View audit logs.
- Manage system settings.

One main admin role is enough for the thesis version.

Keep the authorization structure extendable for receptionist or manager roles.

---

## 6. MAIN MODULES

Create clear modules for:

1. Authentication
2. Customer
3. Room Type
4. Physical Room
5. Facility
6. Room Image
7. Daily Inventory
8. Pricing
9. Promotion
10. Booking
11. Guest
12. Payment
13. DOKU Integration
14. Cancellation
15. Refund
16. Check-in
17. Check-out
18. Report
19. Chatbot
20. Website Content
21. System Setting
22. Audit Log

Keep controllers and Livewire components thin.

Put business logic in service or action classes.

Important services:

- `AvailabilityService`
- `PricingService`
- `BookingService`
- `BookingInventoryService`
- `BookingExpirationService`
- `DokuCheckoutService`
- `DokuSignatureService`
- `DokuNotificationService`
- `CancellationService`
- `RefundService`
- `CheckInService`
- `CheckOutService`
- `ReportService`

---

## 7. ROOM STRUCTURE

Separate room type from physical room.

### Room Type

Example:

- Standard Room
- Deluxe Room
- Family Room

Required data:

- Name
- Slug
- Short description
- Full description
- Adult capacity
- Child capacity
- Maximum guests
- Bed type
- Room size
- Base price
- Facilities
- Policies
- Images
- Publication status

### Physical Room

Example:

- STD-101
- STD-102
- DLX-201

Required data:

- Room number
- Room type
- Floor
- Active status
- Maintenance status
- Internal notes

Customers book a room type.

Admin assigns the physical room during or before check-in.

---

## 8. DAILY INVENTORY

Use daily inventory for each room type.

Create table:

`room_type_inventories`

Minimum columns:

- `id`
- `room_type_id`
- `inventory_date`
- `total_inventory`
- `blocked_inventory`
- `held_inventory`
- `confirmed_inventory`
- timestamps

Add unique constraint:

```text
room_type_id + inventory_date
```

Availability formula:

```text
available =
total_inventory
- blocked_inventory
- held_inventory
- confirmed_inventory
```

For multi-night bookings, the room is available only when every stay date has enough inventory.

Do not count the checkout date as a stay night.

Example:

```text
Check-in: 10 August
Check-out: 13 August

Stay nights:
10 August
11 August
12 August
```

---

## 9. DOUBLE-BOOKING PREVENTION

Prevent double booking in the backend and database.

When creating a booking hold:

1. Start a database transaction.
2. Load inventory for every stay date.
3. Lock the inventory rows using `lockForUpdate()`.
4. Check availability for every date.
5. Reject the booking if one date is unavailable.
6. Increase `held_inventory`.
7. Create the booking.
8. Create booking items.
9. Create nightly price snapshots.
10. Commit the transaction.

Always lock dates in the same order.

Retry limited database deadlocks.

Never allow inventory values below zero.

When only one room is available and two users book at the same time, only one booking may succeed.

---

## 10. BOOKING STATUS

Use separate booking and payment statuses.

Booking statuses:

```text
HELD
PENDING_PAYMENT
CONFIRMED
PAYMENT_REVIEW
CHECKED_IN
CHECKED_OUT
CANCELLED
EXPIRED
NO_SHOW
```

Payment statuses:

```text
UNPAID
PENDING
PAID
FAILED
EXPIRED
REVIEW
REFUND_PENDING
PARTIALLY_REFUNDED
REFUNDED
```

Valid booking transitions:

```text
HELD -> PENDING_PAYMENT
HELD -> EXPIRED

PENDING_PAYMENT -> CONFIRMED
PENDING_PAYMENT -> PAYMENT_REVIEW
PENDING_PAYMENT -> EXPIRED

PAYMENT_REVIEW -> CONFIRMED
PAYMENT_REVIEW -> CANCELLED

CONFIRMED -> CHECKED_IN
CONFIRMED -> CANCELLED
CONFIRMED -> NO_SHOW

CHECKED_IN -> CHECKED_OUT
```

Reject invalid transitions.

Examples:

- `CHECKED_OUT` cannot return to `HELD`.
- `CANCELLED` cannot become `CHECKED_IN`.
- `EXPIRED` cannot become `CONFIRMED` without checking inventory again.
- An unpaid booking cannot be refunded.

Use PHP Enum classes.

---

## 11. ROOM SEARCH

Search form fields:

- Check-in date
- Check-out date
- Adults
- Children
- Number of rooms

Rules:

- Check-in cannot be before today.
- Check-out must be after check-in.
- Minimum stay is one night.
- Default maximum stay is 30 nights.
- Number of rooms must be at least one.
- Guest count must fit room capacity.
- Inactive room types must not appear.
- Maintenance rooms reduce availability.
- Availability must come from daily inventory.

Search results must show:

- Main photo
- Room type name
- Capacity
- Main facilities
- Number of rooms left
- Price per night
- Total stay price
- Cancellation policy
- Room detail button
- Select room button

Do not display availability using only total physical room count.

---

## 12. PRICING

Calculate all prices on the server.

Never trust prices sent from the browser.

Pricing components:

- Base price
- Weekend adjustment
- Seasonal adjustment
- Special date price
- Extra guest charge
- Promotion discount
- Tax
- Service charge
- Final total

Recommended order:

```text
Base nightly price
+ weekend adjustment
+ seasonal adjustment
+ extra guest fee
= subtotal before discount

subtotal before discount
- promotion discount
= subtotal after discount

subtotal after discount
+ tax
+ service charge
= grand total
```

Store money as integer rupiah.

Do not use floating-point values for money.

Create nightly price snapshots in:

`booking_item_nights`

Minimum columns:

- `booking_item_id`
- `stay_date`
- `base_amount`
- `adjustment_amount`
- `discount_amount`
- `tax_amount`
- `final_amount`
- `pricing_metadata`

Changing future room prices must not change an existing booking.

---

## 13. PROMOTIONS

Support:

- Percentage discount
- Fixed discount
- Automatic promotion
- Promotion code
- Room-type restriction
- Booking-date restriction
- Stay-date restriction
- Minimum nights
- Minimum transaction
- Usage limit
- Active status
- Expiry date

Rules:

- Reject expired promotions.
- Reject promotions with exhausted quotas.
- Reject promotions for unsupported room types.
- Reject promotions when minimum nights are not met.
- Recalculate the total on the server.
- Show the latest total before payment.

---

## 14. BOOKING FLOW

Use this flow:

### Step 1 — Search

Customer enters dates and guest count.

### Step 2 — Select room

Customer selects room type and room quantity.

### Step 3 — Customer details

Collect:

- Full name
- Email
- Phone number
- Country
- Guest names
- Expected arrival time
- Special request
- Terms acceptance

### Step 4 — Review

Show:

- Stay dates
- Total nights
- Room type
- Room quantity
- Guest count
- Nightly prices
- Discount
- Tax
- Service charge
- Grand total
- Cancellation policy

### Step 5 — Create hold

Create a 30-minute booking hold.

Required fields:

- Booking code
- Booking status
- Payment status
- Expiration time

### Step 6 — DOKU payment

Create DOKU Checkout from the backend.

Redirect customer to the DOKU payment page.

### Step 7 — Confirmation

Confirm the booking only after a valid DOKU notification is processed by the backend.

The browser callback is not proof of payment.

---

## 15. BOOKING CODE

Generate a unique public booking code.

Example:

```text
NPS-20260810-A7K9P2
```

Rules:

- Must be unique.
- Must not expose the auto-increment database ID.
- Must not be easily predictable.
- Add a unique database index.

Keep these values separate:

- Internal booking ID
- Booking code
- DOKU invoice number
- DOKU request ID

---

## 16. DOKU CHECKOUT

Use DOKU Checkout.

Environment configuration:

```env
DOKU_ENVIRONMENT=sandbox
DOKU_CLIENT_ID=
DOKU_SECRET_KEY=
DOKU_BASE_URL=
DOKU_NOTIFICATION_URL=
DOKU_CALLBACK_URL=
DOKU_PAYMENT_DUE_MINUTES=30
```

Never place credentials directly in source code.

Use Laravel config files.

Do not call `env()` throughout business services.

Create:

- `DokuCheckoutService`
- `DokuSignatureService`
- `DokuNotificationVerifier`
- `DokuPaymentMapper`

Use the latest official DOKU documentation for:

- Endpoint
- Headers
- Signature
- Digest
- Request body
- Notification body
- Payment status
- Callback handling

Do not guess DOKU field names.

When creating payment:

1. Check that the booking is still active.
2. Check that the hold is not expired.
3. Recalculate the booking total.
4. Generate a unique invoice number.
5. Generate a unique request ID.
6. Generate the DOKU signature on the backend.
7. Send the request from the backend.
8. Save the payment URL.
9. Save the expiry time.
10. Save redacted request and response data.
11. Redirect the customer to DOKU.

Never allow the frontend to send the payment amount as the final trusted value.

---

## 17. PAYMENT ATTEMPTS

One booking may have multiple payment attempts.

Create table:

`payment_attempts`

Minimum columns:

- `id`
- `booking_id`
- `provider`
- `invoice_number`
- `request_id`
- `amount`
- `currency`
- `payment_url`
- `status`
- `expires_at`
- `paid_at`
- `failure_code`
- `failure_message`
- `request_payload_redacted`
- `response_payload_redacted`
- timestamps

Rules:

- Only one active attempt at a time.
- Retrying payment must not create another booking.
- Retrying payment must not reserve inventory twice.
- Each new payment attempt may use a new invoice number.
- Keep failed attempts for audit.

---

## 18. DOKU NOTIFICATION

Create notification endpoint:

```text
POST /api/payments/doku/notifications
```

The endpoint must:

1. Read the raw request body.
2. Validate required headers.
3. Verify DOKU signature.
4. Verify client ID.
5. Verify invoice number.
6. Verify amount.
7. Verify currency.
8. Detect duplicate notifications.
9. Store the payment event.
10. Update payment and booking inside a database transaction.
11. Return the correct HTTP response.

Create table:

`payment_events`

Minimum columns:

- `id`
- `provider`
- `payment_attempt_id`
- `provider_request_id`
- `event_type`
- `payload_hash`
- `signature_valid`
- `processing_status`
- `payload_redacted`
- `received_at`
- `processed_at`
- `error_message`

Add a unique constraint to prevent duplicate processing.

Duplicate notifications must not:

- Confirm the booking twice.
- Change inventory twice.
- Send duplicate confirmation emails.
- Create duplicate payment records.

---

## 19. PAYMENT RULES

### Successful payment before expiry

When signature, invoice, amount, and booking are valid:

- Set payment to `PAID`.
- Set booking to `CONFIRMED`.
- Decrease held inventory.
- Increase confirmed inventory.
- Save confirmation time.
- Send confirmation email.
- Record audit log.

Perform all inventory changes in one database transaction.

### Failed payment

- Set payment attempt to `FAILED`.
- Keep the booking active while the hold remains valid.
- Allow another payment attempt.
- Do not confirm inventory.

### Expired hold

When 30 minutes have passed without valid payment:

- Set booking to `EXPIRED`.
- Set payment to `EXPIRED`.
- Release held inventory.
- Disable the old payment URL.
- Require a new booking or safe recovery process.

### Late payment

When payment arrives after expiry:

1. Verify the notification.
2. Lock daily inventory.
3. Check availability again.

When inventory is available:

- Reserve inventory again.
- Confirm the booking.
- Mark it as late-payment recovery.
- Record an audit log.

When inventory is unavailable:

- Set booking to `PAYMENT_REVIEW`.
- Set payment to `REVIEW`.
- Notify admin.
- Require refund or alternative room handling.

### Amount mismatch

When paid amount differs from booking total:

- Do not confirm booking.
- Set booking to `PAYMENT_REVIEW`.
- Set payment to `REVIEW`.
- Notify admin.

### Invalid signature

- Reject the notification.
- Do not update booking.
- Do not update payment.
- Record a security log.

### Browser callback without notification

- Display “Pembayaran sedang diperiksa”.
- Keep the booking pending.
- Do not show a false success message.

---

## 20. SCHEDULER AND QUEUE

Use Laravel Scheduler for:

- Expiring booking holds
- Releasing held inventory
- Expiring payment attempts
- Marking no-show bookings
- Sending check-in reminders
- Cleaning safe temporary data

Run booking expiry checks every minute.

Use Laravel Queue for:

- Booking emails
- Payment emails
- Cancellation emails
- Refund emails
- Check-in reminders
- Report exports

Queued jobs must be safe to retry.

---

## 21. CANCELLATION

Create configurable cancellation policies.

Default development policy:

- Cancellation at least 24 hours before check-in may receive a full refund.
- Cancellation less than 24 hours before check-in does not receive an automatic refund.

Store a policy snapshot in each booking.

Rules:

- `HELD` bookings may be cancelled and inventory released.
- `CONFIRMED` bookings follow the cancellation policy.
- `CHECKED_IN` bookings cannot be cancelled by customers.
- `CHECKED_OUT` bookings cannot be cancelled.
- Cancellation reason is required.
- Admin overrides require a reason.
- All overrides must be audited.

---

## 22. REFUND

Do not treat cancellation as completed refund.

Create table:

`refunds`

Minimum columns:

- `id`
- `booking_id`
- `payment_attempt_id`
- `amount`
- `status`
- `reason`
- `provider_reference`
- `requested_at`
- `processed_at`
- `processed_by`
- `notes`

Statuses:

```text
REQUESTED
APPROVED
REJECTED
PROCESSING
SUCCEEDED
FAILED
```

If automatic DOKU refund is unavailable:

- Allow manual refund recording.
- Do not display refund as successful before admin confirms completion.
- Label it clearly as manual or pending.

Never allow the same payment to be refunded twice beyond the paid amount.

---

## 23. CHECK-IN

Admin may check in only when:

- Booking status is `CONFIRMED`.
- Payment follows hotel policy.
- Check-in date is valid.

During check-in:

1. Select an active physical room.
2. Ensure it belongs to the booked room type.
3. Ensure it is not under maintenance.
4. Ensure it is not assigned to an overlapping booking.
5. Save the room assignment.
6. Save guest identity data.
7. Save actual check-in time.
8. Set booking to `CHECKED_IN`.

Create table:

`room_assignments`

Minimum columns:

- `booking_item_id`
- `room_id`
- `assigned_at`
- `assigned_by`
- `check_in_at`
- `check_out_at`

Do not assign the same physical room to overlapping bookings.

Early check-in requires an admin reason and audit log.

---

## 24. CHECK-OUT

Admin may check out only a `CHECKED_IN` booking.

During check-out:

- Save actual checkout time.
- Close room assignment.
- Set booking to `CHECKED_OUT`.
- Record the admin.
- Record optional extra charges.
- Record late checkout notes when relevant.

Do not automatically charge late checkout unless a configured rule exists.

---

## 25. CHATBOT

Create a database-based FAQ chatbot.

It should answer:

- Room types
- Starting prices
- Facilities
- Hotel location
- Check-in time
- Check-out time
- Cancellation policy
- Payment methods
- Booking process
- Booking status instructions
- Hotel contact

Admin can manage:

- Question
- Answer
- Keywords
- Category
- Priority
- Active status

The chatbot must not:

- Invent room prices.
- Claim a room is available without using `AvailabilityService`.
- Expose customer booking data.
- Confirm payments.
- Change booking status.

When no answer is found:

- Show a fallback message.
- Show hotel contact options.
- Store the unanswered question for admin review.

---

## 26. DATABASE TABLES

Create or adjust these tables:

- `users`
- `customer_profiles`
- `room_types`
- `rooms`
- `amenities`
- `amenity_room_type`
- `room_images`
- `rate_plans`
- `seasonal_rates`
- `promotions`
- `promotion_room_type`
- `room_type_inventories`
- `bookings`
- `booking_guests`
- `booking_items`
- `booking_item_nights`
- `room_assignments`
- `payment_attempts`
- `payment_events`
- `cancellation_requests`
- `refunds`
- `pages`
- `faqs`
- `chatbot_sessions`
- `chatbot_messages`
- `system_settings`
- `audit_logs`

Use:

- Foreign keys
- Unique constraints
- Proper indexes
- PHP enums
- Database transactions
- Safe cascade rules

Do not permanently delete booking and payment history.

---

## 27. IMPORTANT INDEXES

Add indexes for:

- Booking code
- Booking status
- Payment status
- Check-in date
- Check-out date
- Customer email
- Invoice number
- Request ID
- Payment attempt status
- Payment event request ID
- Room type and inventory date
- Physical room number
- Room type relation
- Promotion code

Avoid N+1 queries.

Use eager loading where appropriate.

---

## 28. PUBLIC UI

Create a modern, premium, responsive hotel-booking interface.

Use Booking.com only as interaction inspiration.

Do not copy its branding or layout.

### Homepage

Include:

- Responsive navbar
- Hotel logo
- Hero section
- Room search form
- Hotel advantages
- Featured room types
- Facilities
- Gallery
- Promotions
- Location
- FAQ
- Booking call-to-action
- Footer
- Floating chatbot

### Search results

Include:

- Search summary
- Edit-search button
- Filters
- Sorting
- Room cards
- Total stay price
- Rooms-left information
- Cancellation policy
- Loading skeleton
- Clear empty state

### Room detail

Include:

- Image gallery
- Room description
- Capacity
- Facilities
- Policies
- Price
- Stay summary
- Desktop sticky booking card
- Mobile sticky booking action

### Checkout

Use steps:

1. Select room
2. Guest details
3. Review
4. Payment
5. Confirmation

Show payment countdown.

The backend expiration time remains the source of truth.

### Booking status page

Show:

- Booking code
- Booking status
- Payment status
- Stay dates
- Room details
- Total price
- Status history
- Print button
- Cancellation button when allowed

---

## 29. ADMIN UI

Create a responsive admin dashboard with:

- Sidebar
- Navbar
- Breadcrumb
- Search
- Filter
- Pagination
- Toast messages
- Loading states
- Confirmation dialogs
- Empty states
- Error states
- Export
- Print views
- Consistent back navigation

Dashboard cards:

- Today’s reservations
- Today’s check-ins
- Today’s check-outs
- Pending payments
- Payment reviews
- Revenue
- Occupancy
- Maintenance rooms
- Recent reservations

Charts must use database data.

Do not hardcode dashboard totals.

---

## 30. INVENTORY CALENDAR

Admin inventory calendar must show:

- Total inventory
- Held inventory
- Confirmed inventory
- Blocked inventory
- Available inventory
- Daily price
- Promotion
- Maintenance

Admin can:

- Block inventory
- Reopen inventory
- Change daily price
- Bulk update date ranges
- View related bookings

Rules:

- Blocked inventory cannot exceed total inventory.
- Total inventory cannot be lower than confirmed inventory.
- Inventory changes must not silently cancel confirmed bookings.
- Admin overrides require an audit log.

---

## 31. REPORTS

Create reports for:

1. Reservations
2. Payments
3. Revenue
4. Guests
5. Room usage
6. Occupancy
7. Cancellations
8. Refunds

Filters:

- Date range
- Status
- Room type
- Payment method
- Booking source

Support:

- Table view
- Summary
- Print view
- CSV or Excel export
- PDF export when a stable package is available

Revenue rules:

```text
Gross revenue
- discounts
- refunds
= net revenue
```

Do not count failed or expired payments as revenue.

---

## 32. NOTIFICATIONS

Send email for:

- Booking created
- Payment link created
- Payment successful
- Booking confirmed
- Payment failed
- Booking expired
- Booking cancelled
- Refund processed
- Check-in reminder
- Important admin changes

Use queued emails.

Do not expose credentials or payment secrets.

Do not say payment is successful before backend verification.

---

## 33. AUDIT LOG

Record:

- Admin login
- Room changes
- Price changes
- Inventory changes
- Booking changes
- Payment review
- Cancellation
- Refund
- Check-in
- Check-out
- Settings changes
- Admin override

Store:

- User
- Action
- Entity type
- Entity ID
- Previous data
- New data
- IP address
- User agent
- Timestamp

Redact sensitive information.

---

## 34. SECURITY

Implement:

- CSRF protection
- XSS prevention
- Form Request validation
- Authorization policies
- Login throttling
- API rate limiting
- Password hashing
- Secure sessions
- Safe file uploads
- Webhook signature verification
- Payment idempotency
- Environment-based secrets
- Production error protection
- HTTPS in production
- Personal-data redaction

Never trust:

- Price from frontend
- Payment amount from frontend
- Payment status from URL
- Customer role from request
- Uploaded file name
- Browser callback as proof of payment

Only exclude the DOKU notification route from CSRF when required.

Do not disable CSRF globally.

---

## 35. FILE UPLOADS

For room images and galleries:

- Validate MIME type.
- Validate file size.
- Generate random file names.
- Store metadata.
- Generate thumbnails when useful.
- Prevent executable uploads.
- Remove orphan files safely.
- Support drag-and-drop.
- Support multiple uploads.
- Support image sorting.
- Support main-image selection.
- Show image preview.

---

## 36. TESTS

Create unit and feature tests.

### Unit tests

Test:

- Number of nights
- Pricing
- Weekend rate
- Seasonal rate
- Percentage promotion
- Fixed promotion
- Expired promotion
- Guest capacity
- Booking transitions
- Payment transitions
- Cancellation policy
- Refund calculation
- DOKU digest
- DOKU signature
- Payment event mapping

### Feature tests

Test:

- Available-room search
- Unavailable-room search
- Invalid dates
- Booking hold
- Inventory reduction
- Hold expiration
- Payment success
- Duplicate notification
- Invalid DOKU signature
- Amount mismatch
- Late payment with available inventory
- Late payment without inventory
- Booking cancellation
- Booking access security
- Check-in
- Check-out
- Physical room conflicts
- Revenue calculation

### Concurrency test

Scenario:

- One room remains.
- Two booking requests run nearly at the same time.
- Only one request succeeds.
- Inventory never becomes negative.

---

## 37. SEED DATA

Create development seeders for:

- One admin
- Several customers
- Three room types
- Several physical rooms
- Facilities
- Room photos
- Base prices
- Seasonal prices
- Promotions
- At least 90 days of inventory
- Chatbot FAQs
- Website pages
- Example bookings
- Example payment attempts

Demo credentials are for local development only.

Never use demo credentials in production.

---

## 38. THESIS DOCUMENTATION

Create folder:

```text
docs/
```

Create:

- `01-requirements.md`
- `02-business-rules.md`
- `03-system-architecture.md`
- `04-database-erd.md`
- `05-booking-flow.md`
- `06-doku-payment-flow.md`
- `07-security.md`
- `08-testing-plan.md`
- `09-deployment.md`
- `10-prototype-iterations.md`
- `11-thesis-mapping.md`

Use Mermaid diagrams for:

- Use case
- Booking activity
- Booking sequence
- DOKU notification sequence
- Booking state diagram
- Database ERD
- System architecture

Do not invent interview or evaluation results.

Provide templates for the researcher to enter real data.

---

## 39. PROTOTYPE PHASES

Document development using these iterations:

### Iteration 1

- Homepage
- Room list
- Room detail
- Search form
- Booking interface prototype
- Basic admin dashboard

### Iteration 2

- Database
- Availability
- Pricing
- Booking hold
- Room management
- Customer management

### Iteration 3

- DOKU Checkout
- Payment notification
- Booking confirmation
- Expiration
- Email
- Reports

### Iteration 4

- Cancellation
- Refund
- Check-in
- Check-out
- Chatbot
- Audit log
- Security
- Testing
- UI improvement

For each iteration, document:

- Initial requirement
- Prototype
- User feedback placeholder
- Changes
- Evaluation
- Acceptance status

---

## 40. DEVELOPMENT PHASES

Execute in this order.

### Phase 0 — Audit

- Inspect the existing project.
- Detect installed versions.
- Detect current database structure.
- Detect existing routes.
- Detect working features.
- Detect bugs and technical debt.
- Compare the project against this prompt.
- Create a gap analysis.
- Create `PROJECT_STATUS.md`.

Gap priorities:

- Critical
- High
- Medium
- Low

### Phase 1 — Foundation

- Project configuration
- Authentication
- Authorization
- Public layout
- Admin layout
- Enums
- Settings
- Audit log
- Base tests

### Phase 2 — Rooms

- Room types
- Physical rooms
- Facilities
- Room images
- Website content
- Homepage
- Room listing
- Room detail

### Phase 3 — Inventory and Pricing

- Daily inventory
- Availability
- Pricing
- Seasonal pricing
- Promotions
- Inventory calendar

### Phase 4 — Booking

- Search
- Checkout
- Customer data
- Booking hold
- Nightly price snapshots
- Booking lookup
- Booking expiry

### Phase 5 — DOKU

- DOKU configuration
- Signature
- Checkout request
- Payment attempts
- Callback page
- Notification endpoint
- Idempotency
- Payment review
- Confirmation email

### Phase 6 — Hotel Operations

- Cancellation
- Refund
- Check-in
- Room assignment
- Check-out
- No-show
- Guest records

### Phase 7 — Reports and Chatbot

- Reports
- Export
- Print
- FAQ
- Chatbot
- Admin FAQ management

### Phase 8 — QA

- Unit tests
- Feature tests
- Concurrency tests
- Security review
- Performance review
- Accessibility review
- UI consistency
- Error handling

### Phase 9 — Deployment

- Local setup
- Production setup
- `.env.example`
- Queue
- Scheduler
- Backup
- Documentation
- Demo account
- Final test report
- Handover guide

Do not continue to the next phase while critical errors remain.

---

## 41. REQUIRED COMMANDS

Run the commands appropriate for the project:

```bash
composer install
npm install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan optimize:clear
npm run build
php artisan test
```

For local runtime:

```bash
php artisan serve
php artisan queue:work
php artisan schedule:work
```

Do not run `php artisan migrate:fresh` in production.

---

## 42. PHASE REPORT FORMAT

After every phase, output:

```text
PHASE:
STATUS:

SUMMARY:
- ...

FILES CREATED:
- ...

FILES MODIFIED:
- ...

DATABASE:
- ...

BUSINESS LOGIC:
- ...

TESTS RUN:
- ...

TEST RESULTS:
- ...

BUILD RESULT:
- ...

ERRORS FOUND:
- ...

FIXES:
- ...

REMAINING RISKS:
- ...

NEXT ACTION:
- ...
```

Never report a test as passed unless it was actually executed.

Never report DOKU integration as successful when it only uses mock data.

---

## 43. DEFINITION OF DONE

The project is complete only when:

- Public website works.
- Admin dashboard works.
- Responsive design works.
- Room management works.
- Inventory works.
- Availability search works.
- Pricing works.
- Promotions work.
- Booking hold works.
- Booking expiration works.
- Double booking is prevented.
- DOKU sandbox works.
- DOKU notification verification works.
- Duplicate notification handling works.
- Booking confirmation works.
- Cancellation works.
- Refund recording works.
- Check-in works.
- Physical room assignment works.
- Check-out works.
- Chatbot works.
- Reports work.
- Audit log works.
- Authorization works.
- Migrations work.
- Seeders work.
- Tests pass.
- Frontend build passes.
- No core route is broken.
- No important button is dead.
- No critical console error remains.
- Documentation is complete.
- Local installation is documented.
- Production deployment is documented.

---

## 44. START NOW

Start with **Phase 0 — Project Audit**.

Do the following now:

1. Read all relevant project files.
2. Identify the actual installed framework and versions.
3. Identify the current database schema.
4. Identify routes and existing features.
5. Identify bugs, missing logic, duplicate code, and technical debt.
6. Compare the current project with this master prompt.
7. Create a prioritized gap analysis.
8. Create a file-based implementation plan.
9. Create or update `PROJECT_STATUS.md`.
10. Do not delete working code.
11. Fix critical installation or build errors.
12. Continue to Phase 1 after the audit when no critical blocker exists.
13. Run tests and build after every significant change.
14. Report only work that was actually completed.
