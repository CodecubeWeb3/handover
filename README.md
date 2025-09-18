APP: SAFE HANDOVER
STACK: Laravel 12 (PHP 8.4) + MySQL 8 + Stripe Connect + Pusher/Ably
MODE: Shared hosting. DB queue + cron. No long-running websockets.
check the latest documentation for Laravel 12.

CONTENTS

0) Scope

Architecture

Roles

Core Journeys

Scheduling + Overlap Guards

Booking State Machine

Payments + Shared Pay + Late Fees

Verification Stack

Messaging + Realtime

Data Model + Indexes

API Contract

Jobs + Cron

Installer + Config

Webhooks

Frontend + UX

Admin

Security + Privacy

Observability + Audit

Performance + A11y

Deployment (Shared Hosting)

Feature Flags

Tests

Build Order

Acceptance Criteria

======================================================================
0) SCOPE

Purpose: verify child handovers only.

Each booking = two legs: A→Agent, Agent→B. Return is a new booking.

Non-goals: live child tracking, transport, supervision.

======================================================================

ARCHITECTURE
======================================================================

Backend: Laravel 12, PHP 8.4.

DB: MySQL 8. Spatial types SRID 4326 for POINT/POLYGON.

Queue: database driver. Runner via cron each minute.

Realtime: Pusher or Ably (pusher-compatible).

Payments: Stripe Connect Express. Manual capture PaymentIntents.

Identity: Stripe Identity. Store tokens/results only.

Maps: Leaflet + OpenStreetMap tiles. MySQL spatial for matching.

Storage: S3-compatible for images.

Notifications: Email + SMS. Optional Web Push (PWA).

Admin: Filament.

======================================================================
2) ROLES

parent, operative, admin, moderator.

Principle: only assigned parties access chat and precise meet location.

Country profile controls age, KYC, VAT/GST, retention.

======================================================================
3) CORE JOURNEYS

Parent

Sign up → verify email/phone.

Set country + service area.

Create request + 10-min slots + meet point.

Pre-authorize payment(s).

Attend handover. Rate operative.

Operative

Sign up → ID/KYC.

Define service areas (polygon or postcodes).

Toggle available. Browse nearby requests. Apply. Accept.

Perform scans. Receive payout. View ledger.

Minimal-touch handover

Parent A opens “Pass” link or wallet pass. Shows QR + PIN (TOTP).

Agent scans A. System validates time/geo/token/device. Status = Child with
Agent.

After buffer, Parent B opens pass. Agent scans B. Status = Complete. Funds
captured. Receipts sent.

======================================================================
4) SCHEDULING + OVERLAP GUARDS

Time unit: 10-min slot.

Unique constraints:

booking_slots(request_id, slot_ts) UNIQUE.

applications(operative_id, slot_ts) UNIQUE.

bookings(operative_id, slot_ts) UNIQUE.

operative_holds(operative_id, slot_ts) UNIQUE. TTL-based.

All joins/promotions inside DB transactions.

Waitlist per slot with integer position. Auto-promote on cancel.

Calendar: per request, badges Open / Filled / Waitlist. Heatmap by day.

======================================================================
5) BOOKING STATE MACHINE

States

SCHEDULED → A_WINDOW_OPEN → A_SCANNED → BUFFER → B_WINDOW_OPEN → B_SCANNED
→ COMPLETED

Terminal: NO_SHOW_A, NO_SHOW_B, CANCELED, EXPIRED.

Guards

time_window_ok, geo_ok, token_ok, device_ok.

Timers

tA_grace, tB_grace, t_buf.

Events

open_A_window, scan_A_ok, timer_A_grace_expired, buffer_elapsed, scan_B_ok,
timer_B_grace_expired, cancel_by_{role}, timeout_all.

Safety automation

If A_SCANNED and agent device leaves geofence > N m for > 2 min → freeze.
If not resolved by tB_grace → NO_SHOW_B.

Idempotency

Event key: booking_id + state + event_uuid. Handlers idempotent.

======================================================================
6) PAYMENTS + SHARED PAY + LATE FEES

Accounts

Operatives: Stripe Connect Express.

Platform: application_fee_amount. Destination transfer to operative.

Pre-auth

capture_method=manual.

Shared pay: 2 PaymentIntents (A and B), each ~50% + optional reserve.

Booking active only after both PIs = requires_capture.

Capture

Trigger: B_SCANNED.

Capture both PIs. Include computed late fees. Transfer net to operative.

Late fees (per-country settings)

Params: grace_A, grace_B, wait_cap_A, wait_cap_B, late_fee_base,
late_fee_per_min, travel_stipend, platform_pct, min_capture_pct_if_no_show.

late_minutes_X = clamp(delay_min - grace_X, 0, wait_cap_X - grace_X).

late_fee_X = late_fee_base + late_minutes_X * late_fee_per_min.

No-shows

NO_SHOW_A:

Capture from A: max(min_capture_pct * slot_price, late_fee_A + stipend).

Cancel B PI.

NO_SHOW_B:

Capture B: slot_price + late_fee_B + stipend.

A captured per policy if L1 completed.

Shared-pay failure

Retry window. If one capture fails:

Policy A: request consent to charge other parent for shortfall.

Policy B: platform advance from reserve and recover later.

Configurable in admin.

Invoices

VAT/GST only on platform fee where required. Ledger stores tax calc.

======================================================================
7) VERIFICATION STACK

Tokens

Per-leg rotating QR (30–60 s) + 6-digit TOTP PIN.

Single-use. Role- and leg-bound. Replay blocked.

Geo

Meet POINT (SRID 4326). Geofence 50–100 m. Log accuracy_m.

Device

WebAuthn challenge on agent scan. Device binding required.

Fallbacks

Wallet passes, email magic links, in-app “Show Pass”.

IVR voice code if SMS fails.

Offline PIN on agent via TOTP if no data.

Admin one-time override. All logged.

Parents never meet

Parent B locked until A_SCANNED + buffer. Only “Wait” or “Approach now”.

======================================================================
8) MESSAGING + REALTIME

Private thread per booking. No phone/email until confirmed.

Typing + read receipts via Pusher/Ably.

Attachment filtering. Abuse report.

Broadcast events: slot.updated, waitlist.promoted, scan.ok, scan.fail,
handover.approach, payout.updated.

======================================================================
9) DATA MODEL + INDEXES

Users

users(id, role, email, phone, country, dob, two_factor, verified_at).

Profiles

operatives(user_id, kyc_status, reliability_score).

parents(user_id).

Spatial

areas(id, user_id, geom POLYGON SRID 4326).

requests(id, parent_id, meet_point POINT SRID 4326, notes, status).

time_windows(id, request_id, start_ts, end_ts).

Slots + flow

booking_slots(id, request_id, slot_ts, status).

applications(id, slot_id, operative_id, slot_ts, status, created_at).

operative_holds(id, operative_id, slot_ts, expires_at).

waitlist(id, slot_id, operative_id, position).

bookings(id, slot_id, operative_id, slot_ts, status, meet_qr,
meet_point POINT SRID 4326).

Evidence

checkins(id, booking_id, user_id, kind, lat, lng, accuracy_m, created_at).

messages(id, thread_id, sender_id, body, created_at).

Money + disputes

payments(id, booking_id, currency, amount, fee, intent_id, transfer_id,
status).

disputes(id, booking_id, reason, evidence_uri, resolution).

Governance

verifications(id, user_id, provider, result, reviewed_by, reviewed_at).

sanctions(id, user_id, type, expires_at, reason).

audits(id, actor_id, action, target_type, target_id, meta, created_at).

settings(key, value, scope).

Indexes

UNIQUE(booking_slots.request_id, slot_ts).

UNIQUE(applications.operative_id, slot_ts).

UNIQUE(bookings.operative_id, slot_ts).

UNIQUE(operative_holds.operative_id, slot_ts).

SPATIAL INDEX areas.geom, requests.meet_point, bookings.meet_point.

All FK columns indexed.

======================================================================
10) API CONTRACT

Auth

Sanctum tokens.

Booking scans

POST /api/booking/{id}/scan/A

POST /api/booking/{id}/scan/B

POST /api/booking/{id}/noshow/A

POST /api/booking/{id}/noshow/B

POST /api/booking/{id}/cancel

POST /api/booking/{id}/complete

Requests + matching

POST /api/requests (create request, slots, preauth)

GET /api/requests/nearby (by operative spatial areas)

Applications

POST /api/applications (create hold, unique by operative+slot_ts)

POST /api/applications/{id}/accept

Messaging

POST /api/messages/{thread}/send

Broadcast auth

POST /api/broadcasting/auth

Stripe

POST /api/stripe/webhook

Rules

Idempotency-Key required on all write endpoints.

Times ISO 8601 with tz.

Money in minor units.

Error model

code, message, details[], retryable(bool), idempotency_key.

======================================================================
11) JOBS + CRON

Cron

Every minute: php artisan schedule:run

Jobs

OpenWindowsJob (A_WINDOW_OPEN).

BufferElapsedJob.

ComputeLateFeesJob.

CapturePaymentsJob.

PromoteWaitlistJob.

PurgeExpiredHoldsJob (operative_holds TTL).

GeoAlertJob (agent out of zone).

NotificationRetryJob.

WebhookReplayJob.

======================================================================
12) INSTALLER + CONFIG

Installer steps

Check PHP 8.4 + extensions: pdo_mysql, openssl, mbstring, tokenizer, xml,
ctype, json, gd, curl, bcmath.

Collect DB, APP_URL, mail, queue, Stripe, Pusher/Ably, SMS, S3 keys.

Write .env, php artisan key:generate.

php artisan migrate --force.

Seed admin + defaults + country profiles.

Create storage symlink.

Show cron snippet and webhook URLs. Run test pings.

Env keys (required)

APP_KEY, APP_URL, APP_ENV=production, APP_TIMEZONE=Europe/London.

DB_*.

QUEUE_CONNECTION=database, CACHE_DRIVER=file, SESSION_DRIVER=file.

BROADCAST_DRIVER=pusher and Pusher/Ably keys.

STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET, STRIPE_CONNECT_CLIENT_ID.

MAIL_*.

FILESYSTEM_DISK=s3 and AWS_*.

======================================================================
13) WEBHOOKS

Stripe

Verify signature.

Handle: payment_intent.succeeded, payment_intent.canceled,
charge.refunded, identity.verification_session.*.

Idempotent by event id. Ledger rows appended.

Broadcast

Channel auth for booking.{id} and thread.{id}. Policy checks.

======================================================================
14) FRONTEND + UX

Framework

Bootstrap 5.3 + one custom SCSS theme with CSS variables.

Keep overrides minimal. Dark/light toggle.

Pages (build first)

Auth: register, login, phone/email verify.

Dashboards: parent, operative.

Booking detail: QR screens, scanner, map, evidence.

Request create: area, meet point, slots, preauth.

Profiles: operative and parent. Ratings.

Admin (Filament).

Installer.

Passes

Deep-link “Handover Pass” pages (no login).

Wallet passes optional. Rotating QR + TOTP PIN.

Accessibility

WCAG 2.2 AA. Large taps. Reduced motion. High-contrast.

======================================================================
15) ADMIN

Modules

Users (role, KYC, sanctions).

Bookings (state, evidence viewer).

Payments (intents, transfers, refunds).

Disputes (workflows, decisions).

Country Profiles (age, KYC, VAT/GST, retention).

Feature Flags.

Fees + buffers + geofence.

Content flags + word-filter logs.

Audit viewer.

Caseboard (family timeline).

======================================================================
16) SECURITY + PRIVACY

GDPR/AADC DPIA. DPA. Data minimization. Right-to-erasure.

Encrypt at rest. Field-level encryption for PII.

2FA optional. Required for admins.

Stripe Identity only tokens saved.

WebAuthn for agent scans (bind device).

CSP, HSTS, strict CORS (same-site API). CSRF on web.

Rate limiting, bot/abuse filters, profanity filter in chat.

Authority checks: court orders, exclusion zones, blocklists.

No live child tracking. Coarse trip check-ins optional with consent.

Data retention

Country-level windows in settings. Purge jobs enforce.

======================================================================
17) OBSERVABILITY + AUDIT

Immutable ledger of events:
CHECKIN_A, CHECKIN_B, LATE_A, LATE_B, NO_SHOW_A, NO_SHOW_B,
CAPTURED, REFUNDED, DISPUTE_OPENED, DISPUTE_RESOLVED.

Hash chain per booking (Hn = hash(Hn-1 || event_json)).

Structured logs with request_id + idempotency_key.

Dead-letter tables for jobs and webhooks. Replay tools.

======================================================================
18) PERFORMANCE + A11Y

HTTP/2, Brotli, preconnect to Stripe/Pusher/S3.

Code split. Defer non-critical JS. Minify CSS/JS.

Image lazy load. CDN for assets if available.

Server-side rendering for first paint.

PWA for passes and offline PIN.

Lighthouse 90+ all categories.

======================================================================
19) DEPLOYMENT (SHARED HOSTING)

Upload code. Public webroot points to /public. If not possible, rewrite.

Ensure storage/ and bootstrap/cache writeable.

Composer install locally if host lacks Composer.

Set cron: * * * * * php /path/to/artisan schedule:run > /dev/null 2>&1

HTTPS only. Stripe webhooks reachable.

======================================================================
20) FEATURE FLAGS

enable_waitlist

enable_shared_pay

enable_photo_proof

require_webauthn_agent

enable_travel_stipend

buffer_minutes

geofence_radius_m

slot_minutes

enable_wallet_passes

late_fee_base

late_fee_per_min

platform_pct

min_capture_pct_if_no_show

======================================================================
21) TESTS

Unit

State transitions and guard logic.

Late-fee math edge cases.

Feature

scan A/B success, grace, timeout, geo fail, token replay, device fail.

waitlist promotion correctness.

Integration

Stripe: preauth, capture, transfer, refund, idempotent webhooks.

Pusher/Ably broadcast auth and events.

E2E (Dusk)

Pass pages, QR scan, offline PIN, email link, wallet pass.

Shared pay: both pay, one fails, retry, fallback policies.

Admin toggles.

Load

Spatial matching queries. Calendar slot rendering.

Security

RBAC, CORS, CSP, rate limits, WebAuthn flows.

======================================================================
22) BUILD ORDER

Installer + .env writer + health checks.

Migrations with spatial columns and all unique indexes.

Models with casts and relations. Settings service.

State machine service. Idempotent event handler.

Stripe service: preauth, capture, transfer, refunds.

Scan endpoints with all guards (time, geo, token, device).

Jobs + scheduler (windows, buffer, holds, promotions).

Realtime broadcasting + channel auth.

Parent/Operative dashboards. Booking detail. Pass pages. Scanner.

Admin (Filament): flags, fees, evidence, payments, disputes.

Notifications: SMS/email + fallback router.

Security hardening: CSP, CORS, WebAuthn, rate limits.

Test matrix. Fixture data. Webhook replay tools.

======================================================================
23) ACCEPTANCE CRITERIA

Parents complete a two-leg handover with one tap each and two agent scans.

B cannot approach until A scanned + buffer elapsed.

All scans require time, geo, token, device guards to pass.

Double-booking is impossible by constraints and transactions.

Late/no-show outcomes charge correct party per settings.

Shared pay captures both sides, or follows configured fallback.

Evidence bundle contains GPS, timestamps, token ids, device attest, chat
excerpt, hash chain, and is exportable as PDF.

Admin can toggle policies and fees without code changes.

App runs on shared hosting with DB queue + cron. No Horizon required.

Lighthouse ≥ 90 on Performance, A11y, Best Practices, SEO.

======================================================================
