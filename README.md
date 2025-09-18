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

SAFE HANDOVER — MYSQL 8 DATABASE SCHEMA (FULL FEATURES) — TXT WITH ASCII DIVIDERS Target: Laravel 12, PHP 8.4, MySQL 8, SRID 4326. UTF8MB4. InnoDB. Timestamps stored in UTC (DATETIME(3)). Money stored in integer minor units. ---------------------------------------- GLOBAL SQL MODE / CHARSET (OPTIONAL) ---------------------------------------- SET NAMES utf8mb4; SET time_zone = '+00:00'; -- CREATE DATABASE safe_handover CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci; -- USE safe_handover; ======================================================================================================================== USERS, ROLES, PROFILES, AUTH AUX ======================================================================================================================== ---------------------------------------- TABLE: users ---------------------------------------- CREATE TABLE users ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, role ENUM('parent','operative','admin','moderator') NOT NULL, name VARCHAR(191) NULL, email VARCHAR(191) NOT NULL UNIQUE, email_verified_at DATETIME(3) NULL, phone VARCHAR(32) NULL, phone_verified_at DATETIME(3) NULL, country CHAR(2) NOT NULL, dob DATE NULL, password VARCHAR(255) NULL, two_factor_secret TEXT NULL, two_factor_recovery_codes TEXT NULL, stripe_customer_id VARCHAR(64) NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3) ) ENGINE=InnoDB; ---------------------------------------- TABLE: operatives ---------------------------------------- CREATE TABLE operatives ( user_id BIGINT UNSIGNED PRIMARY KEY, kyc_status ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified', reliability_score DECIMAL(5,2) NOT NULL DEFAULT 0.00, stripe_connect_id VARCHAR(64) NULL, languages JSON NULL, bio VARCHAR(500) NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), CONSTRAINT fk_operatives_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ) ENGINE=InnoDB; ---------------------------------------- TABLE: parents ---------------------------------------- CREATE TABLE parents ( user_id BIGINT UNSIGNED PRIMARY KEY, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), CONSTRAINT fk_parents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ) ENGINE=InnoDB; ---------------------------------------- TABLE: webauthn_credentials (agent device binding) ---------------------------------------- CREATE TABLE webauthn_credentials ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, credential_id VARBINARY(255) NOT NULL UNIQUE, public_key LONGBLOB NOT NULL, transports VARCHAR(191) NULL, sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0, last_used_at DATETIME(3) NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), CONSTRAINT fk_webauthn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX idx_webauthn_user (user_id) ) ENGINE=InnoDB; ======================================================================================================================== 2) GEO AREAS, REQUESTS, WINDOWS, SLOTS, HOLDS, APPLICATIONS, WAITLIST ---------------------------------------- TABLE: areas (service polygons) ---------------------------------------- CREATE TABLE areas ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, geom POLYGON SRID 4326 NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), CONSTRAINT fk_areas_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, SPATIAL INDEX sidx_areas_geom (geom), INDEX idx_areas_user (user_id) ) ENGINE=InnoDB; ---------------------------------------- TABLE: requests (parent posts) ---------------------------------------- CREATE TABLE requests ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, parent_id BIGINT UNSIGNED NOT NULL, meet_point POINT SRID 4326 NULL, notes TEXT NULL, status ENUM('open','partially_filled','closed','canceled') NOT NULL DEFAULT 'open', created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), CONSTRAINT fk_requests_parent FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE, SPATIAL INDEX sidx_requests_meet (meet_point), INDEX idx_requests_parent (parent_id), INDEX idx_requests_status (status) ) ENGINE=InnoDB; ---------------------------------------- TABLE: time_windows (broad windows on request) ---------------------------------------- CREATE TABLE time_windows ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, request_id BIGINT UNSIGNED NOT NULL, start_ts DATETIME(3) NOT NULL, end_ts DATETIME(3) NOT NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), CONSTRAINT fk_windows_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE, INDEX idx_windows_req (request_id), INDEX idx_windows_span (start_ts,end_ts) ) ENGINE=InnoDB; ---------------------------------------- TABLE: booking_slots (10-min units) ---------------------------------------- CREATE TABLE booking_slots ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, request_id BIGINT UNSIGNED NOT NULL, slot_ts DATETIME(3) NOT NULL, status ENUM('Open','Filled','Waitlist') NOT NULL DEFAULT 'Open', created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), CONSTRAINT fk_slots_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE, UNIQUE KEY uq_slots_req_ts (request_id, slot_ts), INDEX idx_slots_ts (slot_ts), INDEX idx_slots_status (status) ) ENGINE=InnoDB; ---------------------------------------- TABLE: operative_holds (race protection) ---------------------------------------- CREATE TABLE operative_holds ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, operative_id BIGINT UNSIGNED NOT NULL, slot_ts DATETIME(3) NOT NULL, expires_at DATETIME(3) NOT NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), CONSTRAINT fk_holds_op FOREIGN KEY (operative_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY uq_holds_op_ts (operative_id, slot_ts), INDEX idx_holds_exp (expires_at), INDEX idx_holds_ts (slot_ts) ) ENGINE=InnoDB; ---------------------------------------- TABLE: applications (operative applies) ---------------------------------------- CREATE TABLE applications ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, slot_id BIGINT UNSIGNED NOT NULL, operative_id BIGINT UNSIGNED NOT NULL, slot_ts DATETIME(3) NOT NULL, -- denormalized for UNIQUE status ENUM('Pending','Accepted','Rejected','Withdrawn') NOT NULL DEFAULT 'Pending', created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), CONSTRAINT fk_apps_slot FOREIGN KEY (slot_id) REFERENCES booking_slots(id) ON DELETE CASCADE, CONSTRAINT fk_apps_op FOREIGN KEY (operative_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY uq_apps_op_ts (operative_id, slot_ts), INDEX idx_apps_slot (slot_id), INDEX idx_apps_status (status) ) ENGINE=InnoDB; ---------------------------------------- TABLE: waitlist (per-slot queue) ---------------------------------------- CREATE TABLE waitlist ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, slot_id BIGINT UNSIGNED NOT NULL, operative_id BIGINT UNSIGNED NOT NULL, position INT NOT NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), CONSTRAINT fk_wait_slot FOREIGN KEY (slot_id) REFERENCES booking_slots(id) ON DELETE CASCADE, CONSTRAINT fk_wait_op FOREIGN KEY (operative_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY uq_wait_slot_op (slot_id, operative_id), UNIQUE KEY uq_wait_slot_pos (slot_id, position), INDEX idx_wait_slot (slot_id) ) ENGINE=InnoDB; ======================================================================================================================== 3) BOOKINGS, CHECK-INS, EVENTS LEDGER, RATINGS ---------------------------------------- TABLE: bookings ---------------------------------------- CREATE TABLE bookings ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, slot_id BIGINT UNSIGNED NOT NULL, operative_id BIGINT UNSIGNED NOT NULL, slot_ts DATETIME(3) NOT NULL, -- denormalized for UNIQUE status ENUM( 'Scheduled','A_WINDOW_OPEN','A_SCANNED','BUFFER', 'B_WINDOW_OPEN','B_SCANNED','COMPLETED', 'NO_SHOW_A','NO_SHOW_B','CANCELED','EXPIRED','FROZEN' ) NOT NULL DEFAULT 'Scheduled', meet_qr VARCHAR(191) NULL, meet_point POINT SRID 4326 NULL, buffer_minutes INT NOT NULL DEFAULT 2, geofence_radius_m INT NOT NULL DEFAULT 100, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), CONSTRAINT fk_book_slot FOREIGN KEY (slot_id) REFERENCES booking_slots(id) ON DELETE CASCADE, CONSTRAINT fk_book_op FOREIGN KEY (operative_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY uq_book_op_ts (operative_id, slot_ts), SPATIAL INDEX sidx_book_meet (meet_point), INDEX idx_book_status (status), INDEX idx_book_slot (slot_id) ) ENGINE=InnoDB; ---------------------------------------- TABLE: checkins (GPS + scans + events-of-proof) ---------------------------------------- CREATE TABLE checkins ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, booking_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NULL, kind ENUM('A_SCAN','B_SCAN','PHOTO','AGENT_OUT_OF_ZONE','OVERRIDE','A_NOSHOW','B_NOSHOW') NOT NULL, lat DECIMAL(9,6) NULL, lng DECIMAL(9,6) NULL, accuracy_m INT NULL, token_id VARCHAR(64) NULL, -- QR/TOTP token identifier device_attested TINYINT(1) NOT NULL DEFAULT 0, note VARCHAR(500) NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), CONSTRAINT fk_checkins_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE, CONSTRAINT fk_checkins_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL, INDEX idx_checkins_booking (booking_id), INDEX idx_checkins_kind (kind), INDEX idx_checkins_time (created_at) ) ENGINE=InnoDB; ---------------------------------------- TABLE: booking_events (immutable ledger + hash chain) ---------------------------------------- CREATE TABLE booking_events ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, booking_id BIGINT UNSIGNED NOT NULL, event_type ENUM( 'STATE_CHANGE','CHECKIN_A','CHECKIN_B', 'LATE_A','LATE_B','NO_SHOW_A','NO_SHOW_B', 'CAPTURED','REFUNDED','DISPUTE_OPENED','DISPUTE_RESOLVED', 'GEO_FREEZE','OVERRIDE' ) NOT NULL, payload_json JSON NOT NULL, chain_index INT NOT NULL, prev_hash VARBINARY(64) NULL, this_hash VARBINARY(64) NOT NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), CONSTRAINT fk_bkevt_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE, UNIQUE KEY uq_bkevt_chain (booking_id, chain_index), INDEX idx_bkevt_type (event_type), INDEX idx_bkevt_time (created_at) ) ENGINE=InnoDB; ---------------------------------------- TABLE: ratings ---------------------------------------- CREATE TABLE ratings ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, booking_id BIGINT UNSIGNED NOT NULL, rater_id BIGINT UNSIGNED NOT NULL, ratee_id BIGINT UNSIGNED NOT NULL, role ENUM('parent_rates_operative','operative_rates_parent') NOT NULL, stars TINYINT NOT NULL CHECK (stars BETWEEN 1 AND 5), tag VARCHAR(50) NULL, comment VARCHAR(500) NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), CONSTRAINT fk_ratings_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE, CONSTRAINT fk_ratings_rater FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_ratings_ratee FOREIGN KEY (ratee_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY uq_ratings_once (booking_id, rater_id, role), INDEX idx_ratings_ratee (ratee_id) ) ENGINE=InnoDB; ======================================================================================================================== 4) MESSAGING, ATTACHMENTS, REPORTS ---------------------------------------- TABLE: message_threads ---------------------------------------- CREATE TABLE message_threads ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, booking_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), CONSTRAINT fk_threads_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE, UNIQUE KEY uq_thread_per_booking (booking_id) ) ENGINE=InnoDB; ---------------------------------------- TABLE: messages ---------------------------------------- CREATE TABLE messages ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, thread_id BIGINT UNSIGNED NOT NULL, sender_id BIGINT UNSIGNED NOT NULL, body TEXT NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), CONSTRAINT fk_msgs_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE, CONSTRAINT fk_msgs_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE, INDEX idx_msgs_thread_time (thread_id, created_at), FULLTEXT INDEX ftx_msgs_body (body) ) ENGINE=InnoDB; ---------------------------------------- TABLE: message_attachments ---------------------------------------- CREATE TABLE message_attachments ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, message_id BIGINT UNSIGNED NOT NULL, storage_disk VARCHAR(50) NOT NULL, storage_path VARCHAR(255) NOT NULL, mime VARCHAR(100) NOT NULL, bytes BIGINT UNSIGNED NOT NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), CONSTRAINT fk_msgatt_msg FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE, INDEX idx_msgatt_msg (message_id) ) ENGINE=InnoDB; ---------------------------------------- TABLE: message_flags (abuse reports) ---------------------------------------- CREATE TABLE message_flags ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, message_id BIGINT UNSIGNED NOT NULL, reporter_id BIGINT UNSIGNED NOT NULL, reason VARCHAR(191) NOT NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), CONSTRAINT fk_msgflags_msg FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE, CONSTRAINT fk_msgflags_user FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY uq_msg_flag_once (message_id, reporter_id) ) ENGINE=InnoDB; ======================================================================================================================== 5) PAYMENTS, SHARED PAY, TRANSFERS, DISPUTES ---------------------------------------- TABLE: payments (aggregate per booking) ---------------------------------------- CREATE TABLE payments ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, booking_id BIGINT UNSIGNED NOT NULL, currency CHAR(3) NOT NULL, amount_total BIGINT UNSIGNED NOT NULL, -- slot price (minor units) platform_fee BIGINT UNSIGNED NOT NULL DEFAULT 0, late_fee_a BIGINT UNSIGNED NOT NULL DEFAULT 0, late_fee_b BIGINT UNSIGNED NOT NULL DEFAULT 0, travel_stipend_a BIGINT UNSIGNED NOT NULL DEFAULT 0, travel_stipend_b BIGINT UNSIGNED NOT NULL DEFAULT 0, status ENUM('preauthorized','captured','refunded','canceled', 'payout_pending','payout_settled','failed') NOT NULL DEFAULT 'preauthorized', created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE, INDEX idx_payments_booking (booking_id), INDEX idx_payments_status (status) ) ENGINE=InnoDB; ---------------------------------------- TABLE: payment_intents (per payer, supports shared pay) ---------------------------------------- CREATE TABLE payment_intents ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, booking_id BIGINT UNSIGNED NOT NULL, payer_id BIGINT UNSIGNED NOT NULL, -- Parent A or B role ENUM('A','B') NOT NULL, stripe_pi_id VARCHAR(64) NOT NULL, amount_auth BIGINT UNSIGNED NOT NULL, -- authorized amount amount_captured BIGINT UNSIGNED NOT NULL DEFAULT 0, app_fee_piece BIGINT UNSIGNED NOT NULL DEFAULT 0, status ENUM('requires_capture','captured','canceled','refunded','failed') NOT NULL DEFAULT 'requires_capture', created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), CONSTRAINT fk_pi_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE, CONSTRAINT fk_pi_payer FOREIGN KEY (payer_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY uq_pi_stripe (stripe_pi_id), UNIQUE KEY uq_pi_booking_role (booking_id, role), INDEX idx_pi_status (status) ) ENGINE=InnoDB; ---------------------------------------- TABLE: transfers (Stripe destination transfers to operative) ---------------------------------------- CREATE TABLE transfers ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, booking_id BIGINT UNSIGNED NOT NULL, operative_id BIGINT UNSIGNED NOT NULL, stripe_transfer_id VARCHAR(64) NULL, amount BIGINT UNSIGNED NOT NULL, status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending', created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), CONSTRAINT fk_trans_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE, CONSTRAINT fk_trans_op FOREIGN KEY (operative_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE KEY uq_trans_booking (booking_id), INDEX idx_trans_status (status) ) ENGINE=InnoDB; ---------------------------------------- TABLE: disputes ---------------------------------------- CREATE TABLE disputes ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, booking_id BIGINT UNSIGNED NOT NULL, reason VARCHAR(191) NOT NULL, evidence_uri VARCHAR(255) NULL, resolution ENUM('pending','partial_refund','refund','capture_upheld','ex_gratia','rejected') NOT NULL DEFAULT 'pending', created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), CONSTRAINT fk_disputes_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE, INDEX idx_disputes_status (resolution) ) ENGINE=InnoDB; ======================================================================================================================== 6) IDENTITY, SANCTIONS, GOVERNANCE, SETTINGS ---------------------------------------- TABLE: verifications (KYC + results) ---------------------------------------- CREATE TABLE verifications ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, provider ENUM('stripe_identity','onfido','veriff') NOT NULL, provider_ref VARCHAR(100) NOT NULL, result ENUM('pending','approved','rejected','expired') NOT NULL DEFAULT 'pending', reviewed_by BIGINT UNSIGNED NULL, reviewed_at DATETIME(3) NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), CONSTRAINT fk_verif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_verif_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL, INDEX idx_verif_user (user_id), INDEX idx_verif_provider (provider, provider_ref) ) ENGINE=InnoDB; ---------------------------------------- TABLE: sanctions ---------------------------------------- CREATE TABLE sanctions ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, type ENUM('warning','strike','suspension','ban') NOT NULL, reason VARCHAR(255) NULL, expires_at DATETIME(3) NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), CONSTRAINT fk_sanctions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX idx_sanctions_user (user_id), INDEX idx_sanctions_exp (expires_at) ) ENGINE=InnoDB; ---------------------------------------- TABLE: audits (RBAC + changes) ---------------------------------------- CREATE TABLE audits ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, actor_id BIGINT UNSIGNED NULL, action VARCHAR(100) NOT NULL, target_type VARCHAR(100) NOT NULL, target_id BIGINT UNSIGNED NULL, meta JSON NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), CONSTRAINT fk_audits_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL, INDEX idx_audits_target (target_type, target_id), INDEX idx_audits_time (created_at) ) ENGINE=InnoDB; ---------------------------------------- TABLE: settings (feature flags + config) ---------------------------------------- CREATE TABLE settings ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, scope_type ENUM('global','country','user') NOT NULL DEFAULT 'global', scope_id BIGINT UNSIGNED NULL, key VARCHAR(100) NOT NULL, value JSON NOT NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3), UNIQUE KEY uq_settings (scope_type, scope_id, key), INDEX idx_settings_key (key) ) ENGINE=InnoDB; ---------------------------------------- TABLE: country_profiles (policy per country) ---------------------------------------- CREATE TABLE country_profiles ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, country CHAR(2) NOT NULL UNIQUE, legal_age_min TINYINT NOT NULL DEFAULT 18, kyc_level ENUM('none','basic','enhanced') NOT NULL DEFAULT 'basic', vat_on_platform TINYINT(1) NOT NULL DEFAULT 1, retention_days INT NOT NULL DEFAULT 365, grace_a_min INT NOT NULL DEFAULT 5, grace_b_min INT NOT NULL DEFAULT 5, wait_cap_a_min INT NOT NULL DEFAULT 15, wait_cap_b_min INT NOT NULL DEFAULT 15, geofence_m INT NOT NULL DEFAULT 100, buffer_min INT NOT NULL DEFAULT 2, slot_minutes INT NOT NULL DEFAULT 10, late_fee_base BIGINT UNSIGNED NOT NULL DEFAULT 300, -- e.g., 300 = £3.00 late_fee_per_min BIGINT UNSIGNED NOT NULL DEFAULT 50, -- e.g., 50 = £0.50 travel_stipend BIGINT UNSIGNED NOT NULL DEFAULT 200, -- e.g., £2.00 min_capture_pct_no_show INT NOT NULL DEFAULT 50, -- percent platform_pct INT NOT NULL DEFAULT 10, -- percent created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3) ) ENGINE=InnoDB; ======================================================================================================================== 7) WALLET / TOKEN SUPPORT (TOTP PER LEG, OPTIONAL) ---------------------------------------- TABLE: handover_tokens (one per leg) ---------------------------------------- CREATE TABLE handover_tokens ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, booking_id BIGINT UNSIGNED NOT NULL, leg ENUM('A','B') NOT NULL, totp_secret_hash VARBINARY(64) NOT NULL, rotated_at DATETIME(3) NOT NULL, used_at DATETIME(3) NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), CONSTRAINT fk_tokens_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE, UNIQUE KEY uq_tokens_leg (booking_id, leg) ) ENGINE=InnoDB; ======================================================================================================================== 8) LARAVEL INFRA TABLES (REQUIRED FOR QUEUES/AUTH/NOTIFICATIONS) ---------------------------------------- TABLE: migrations ---------------------------------------- CREATE TABLE migrations ( id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(191) NOT NULL, batch INT NOT NULL ) ENGINE=InnoDB; ---------------------------------------- TABLE: jobs ---------------------------------------- CREATE TABLE jobs ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, queue VARCHAR(191) NOT NULL, payload LONGTEXT NOT NULL, attempts TINYINT UNSIGNED NOT NULL, reserved_at INT UNSIGNED NULL, available_at INT UNSIGNED NOT NULL, created_at INT UNSIGNED NOT NULL, INDEX idx_jobs_queue (queue) ) ENGINE=InnoDB; ---------------------------------------- TABLE: failed_jobs ---------------------------------------- CREATE TABLE failed_jobs ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, uuid VARCHAR(191) NOT NULL UNIQUE, connection TEXT NOT NULL, queue TEXT NOT NULL, payload LONGTEXT NOT NULL, exception LONGTEXT NOT NULL, failed_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ) ENGINE=InnoDB; ---------------------------------------- TABLE: job_batches ---------------------------------------- CREATE TABLE job_batches ( id VARCHAR(191) PRIMARY KEY, name VARCHAR(191) NOT NULL, total_jobs INT NOT NULL, pending_jobs INT NOT NULL, failed_jobs INT NOT NULL, failed_job_ids LONGTEXT NOT NULL, options MEDIUMTEXT NULL, cancelled_at INT NULL, created_at INT NOT NULL, finished_at INT NULL ) ENGINE=InnoDB; ---------------------------------------- TABLE: notifications ---------------------------------------- CREATE TABLE notifications ( id CHAR(36) PRIMARY KEY, type VARCHAR(191) NOT NULL, notifiable_type VARCHAR(191) NOT NULL, notifiable_id BIGINT UNSIGNED NOT NULL, data JSON NOT NULL, read_at DATETIME(3) NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), INDEX idx_notifiable (notifiable_type, notifiable_id) ) ENGINE=InnoDB; ---------------------------------------- TABLE: personal_access_tokens (Sanctum) ---------------------------------------- CREATE TABLE personal_access_tokens ( id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, tokenable_type VARCHAR(191) NOT NULL, tokenable_id BIGINT UNSIGNED NOT NULL, name VARCHAR(191) NOT NULL, token CHAR(64) NOT NULL UNIQUE, abilities TEXT NULL, last_used_at DATETIME(3) NULL, expires_at DATETIME(3) NULL, created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), INDEX idx_tokens_tokenable (tokenable_type, tokenable_id) ) ENGINE=InnoDB; ---------------------------------------- TABLE: password_reset_tokens ---------------------------------------- CREATE TABLE password_reset_tokens ( email VARCHAR(191) PRIMARY KEY, token VARCHAR(191) NOT NULL, created_at DATETIME(3) NULL ) ENGINE=InnoDB; ======================================================================================================================== 9) INTEGRITY, DEFAULT SEEDS, AND NOTES -- Integrity: -- 1) App must keep applications.slot_ts and bookings.slot_ts equal to slot.slot_ts. -- 2) All DATETIME(3) in UTC. -- 3) Money fields are BIGINT minor units (e.g., pence). -- 4) SPATIAL SRID 4326 set on POINT/POLYGON. -- Minimal seed suggestions: -- country_profiles row for GB with defaults. -- settings global feature flags: -- enable_waitlist=true, enable_shared_pay=true, enable_photo_proof=false, -- require_webauthn_agent=true, enable_travel_stipend=true, -- buffer_minutes=2, geofence_radius_m=100, slot_minutes=10, -- late_fee_base=300, late_fee_per_min=50, platform_pct=10, -- min_capture_pct_if_no_show=50. -- Indices summary already included per table. ======================================================================================================================== 10) DROP ORDER (FOR ROLLBACKS) -- Reverse dependencies: -- booking_events, checkins, ratings, message_attachments, message_flags, -- messages, message_threads, transfers, payment_intents, payments, disputes, -- handover_tokens, bookings, waitlist, applications, operative_holds, -- booking_slots, time_windows, requests, areas, verifications, sanctions, -- audits, operatives, parents, webauthn_credentials, users, -- notifications, personal_access_tokens, password_reset_tokens, -- job_batches, failed_jobs, jobs, migrations, settings, country_profiles. ======================================================================================================================== END OF SCHEMA
