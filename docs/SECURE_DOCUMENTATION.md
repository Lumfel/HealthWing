# HealthWing Veterinary Clinic System - Security & Architecture Documentation

## 1. Directory Structure

```text
VetSystem/
├── admin/                  Head Veterinarian dashboard pages, auth guard, and reporting endpoints
├── api/                    JSON APIs, asset delivery proxy, and receipt ledger API
├── archives/               Restricted archive storage (blocked from direct HTTP access)
│   └── owner_receipts/     AES-256-GCM encrypted 365-day owner receipt copies
├── assets/
│   ├── css/                Application stylesheets (CS.css, pen.css, hero-glass.css, loader.css)
│   ├── icons/              Clinical service SVG icons
│   ├── images/             Clinic photography and UI branding assets
│   └── js/                 Frontend scripts (JS.js, script.js, call.js, is.js, glass-shader.js, loader.js)
├── database/               Canonical InnoDB schema and seed SQL files
├── docs/                   Documentation and architectural references
├── emergency_uploads/      Emergency case photo uploads (PHP execution disabled)
├── includes/               Core shared engines (db, security_crypto, scheduling_engine, receipt_blockchain, navbar, turnstile)
├── pet_photos/             Pet profile photo uploads (PHP execution disabled)
├── rate_limit_data/        Sliding-window rate-limit logs and encrypted cache/sessions (HTTP denied)
├── vendor/                 Self-hosted frontend dependencies (Tailwind, Flowbite, Swiper, Chart.js, Motion, Alpine)
├── login.php               Unified sign-in portal (Pet Owner, Staff Veterinarian, Head Veterinarian)
├── register.php            Pet owner registration portal
├── register_vet.php        Veterinarian registration request portal (requires PRC license number)
├── choose_role.php         Account role selection page
├── services.php            Consolidated clinical services hub
├── receipts.php            Protected clinic receipts and blockchain proof viewer
└── ws_server.php           Authenticated RFC 6455 WebSocket server
```

## 2. Security Controls

### 2.1 Three-Role RBAC & Clinical Accountability
- The standalone `admin` table and routes are retired in favor of a unified three-role architecture enforced via `hw_require_role([...])`:
  - `owner` (`owner` table): Pet owners managing pets, appointments, emergencies, ratings, and receipts.
  - `vet_staff` (`veterinarian` table, `Role_Level = 'vet_staff'`): Licensed staff veterinarians operating the clinical workstation (`vetindex.php`).
  - `head_vet` (`veterinarian` table, `Role_Level = 'head_vet'`): Licensed Head Veterinarian with full clinical and administrative console access (`admin/ADMIN_VET_1.php`).
- Every veterinarian requires a unique `PRC_License_No` (`UNIQUE KEY uq_vet_prc`).
- Global case-insensitive `username` and `email` uniqueness is enforced across both `owner` and `veterinarian` under the `hw_identity_lock` advisory lock.
- Clinical checkups (`pet_checkup.Veterinarian_ID`) and clinical receipts (`receipt_blocks.Attending_Vet_ID`) strictly require a verified attending veterinarian and PRC license number.

### 2.2 Transactional Concurrency & Data Integrity
- **Appointment Scheduling (`hw_schedule_lock`)**: Slot availability verification and inserts in `submit_booking.php`, `update_sched_status.php`, and `api/scheduler.php` run inside an InnoDB transaction guarded by MySQL advisory lock `GET_LOCK('hw_schedule_lock', 5)` (always released in `finally`) plus `SELECT ... FOR UPDATE` on `veterinarian`, `room`, and overlapping `schedule` rows.
- **Emergency Triage (`hw_triage_lock`)**: Emergency submissions, severity overrides, preemption, and status transitions in `submit_emergency.php`, `update_status.php`, `admin/update_emergency_status.php`, `api/triage_engine.php`, and `includes/scheduling_engine.php` run inside a single InnoDB transaction guarded by `GET_LOCK('hw_triage_lock', 5)` with `SELECT ... FOR UPDATE` locks on active `emergency_booking`, `room`, `veterinarian`, and `schedule` rows.
- **Non-Destructive Foreign Keys & RA 10173 Compliance**: Clinical history tables (`pet`, `pet_checkup`, `schedule`, `emergency_booking`) use `ON DELETE RESTRICT` or `ON DELETE SET NULL` instead of `ON DELETE CASCADE`, paired with `Deleted_At` and `Anonymized_At` soft-delete / PII-anonymization columns.

### 2.3 AES-256-GCM Encryption & Master Key Isolation
- All session cookies (`hw_auth_gcm`, `hw_user_gcm`, `hw_cf_clearance_gcm`), medical checkup fields, pet records, server caches, and owner receipt archives are encrypted with `aes-256-gcm` (`96-bit` CSPRNG IV and `128-bit` authentication tag).
- `hw_get_master_key()` resolves `HW_MASTER_KEY` from the environment first and otherwise persists the random 256-bit key outside the document root (`.healthwing_secrets/.hw_master_key` in the parent of the webroot, with system temp directory fallback).
- Encryption fails closed: `hw_encrypt_gcm()` throws a `RuntimeException` if OpenSSL AES-256-GCM is unavailable rather than falling back to plaintext.
- Persistent sliding-window cookies use a 90-day TTL with `HttpOnly`, `SameSite=Lax`, and automatic `Secure` flag when HTTPS or `X-Forwarded-Proto: https` is active.

### 2.4 Session-Bound CSRF Protection & WebSocket HMAC Authentication
- `includes/security_crypto.php` provides `hw_csrf_token()`, `hw_csrf_verify()`, and `hw_require_csrf()`.
- Every page embeds `<meta name="hw-csrf-token">`, HTML POST forms carry a hidden `csrf_token` field, and `assets/js/loader.js` automatically injects `X-CSRF-Token` into state-changing `fetch()` requests.
- `api/chat_api.php` issues short-lived HMAC-SHA256 tokens (`hw_generate_ws_token`) encoding the verified server-side role (`owner`, `vet_staff`, or `head_vet`), user ID, display name, allowed channels, and expiration timestamp; `ws_server.php` verifies the signed token via `hw_verify_ws_token()` before permitting subscriptions or broadcasts.

### 2.5 Cloudflare Turnstile, Trusted-Proxy Rate Limiting & Apache Hardening
- `includes/turnstile.php` restricts Cloudflare Always-Pass test keys and offline fail-open strictly to verified localhost requests (`hw_is_local_request()`).
- Pre-clearance cookies (`hw_cf_clearance_gcm`) validate both client IP (`hw_get_client_ip()`) and challenge action before bypassing Turnstile verification.
- The interactive Turnstile diagnostic console on `turnstile.php` is restricted to `head_vet` users in non-local environments.
- `hw_get_client_ip()` and `rate_limit.php` strictly use `REMOTE_ADDR`, honoring `HTTP_X_FORWARDED_FOR` only when `REMOTE_ADDR` matches an explicit `HW_TRUSTED_PROXIES` allowlist.
- All database access flows through `hw_get_db()` in `includes/db.php` with generic error messages.
- Uploaded images in `upload_pet_photo.php` and `submit_emergency.php` are verified with `finfo_file(FILEINFO_MIME_TYPE)` and `getimagesize()`, renamed randomly, and stored in folders where PHP execution is disabled (`php_flag engine off`).
- Root `.htaccess` applies `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, and HSTS (`HTTPS` and `X-Forwarded-Proto: https`).
- Direct browser access (`403 Forbidden`) blocks `.html`, `.htm`, `.css`, `.js`, `.map`, `.sql`, `.json`, `.gcm`, `.key`, `.md`, `.bat`, `.sh`, `.env`, `.git`, `composer.*`, and `.hw_master_key`. Embedded stylesheets and scripts are served exclusively through `api/asset_proxy.php`.
- `archives/`, `docs/`, `includes/`, `database/`, and `rate_limit_data/` deny all direct HTTP requests via `.htaccess`.

## 3. Receipt Blockchain, 1-Year Encrypted Archive & Anti-Screenshot Viewer
- Completed appointments (`update_sched_status.php`), completed emergencies (`update_status.php`, `admin/update_emergency_status.php`), and clinician-issued receipts (`api/receipt_api.php`) mint an immutable block in `receipt_blocks`.
- `hw_receipt_issue()` serializes Genesis Block `#0` and subsequent appends inside an InnoDB transaction guarded by `GET_LOCK('hw_receipt_ledger', 5)` (always released in `finally`) and enforces idempotency via `UNIQUE KEY uq_receipt_ref (Reference_Type, Reference_ID)`.
- Each block computes a SHA-256 `Merkle_Root` over the receipt line items and metadata, links to the previous block (`Prev_Hash`), records `Attending_Vet_ID`, `Attending_Vet_Name`, and `Attending_Clinician_PRC`, and computes a chained `Block_Hash`.
- An AES-256-GCM encrypted owner copy is written to `archives/owner_receipts/<Receipt_Code>.gcm` with a strict 365-day (`Expires_At`) retention limit; after 365 days, `hw_receipt_purge_expired()` unlinks the encrypted archive file and marks `Status = 'PURGED_1YR'` while preserving the hash-chained ledger row intact.
- `receipts.php` enforces anti-screenshot controls: focus-blur and privacy veil on `blur` / `visibilitychange` / `PrintScreen` and capture shortcuts, clipboard clearing attempts, disabled context menu / selection / drag / copy, dynamic user watermarks, and `@media print` blackout.

## 4. Environment Configuration & Seed Accounts

Supported optional environment variables for production deployments:
- `HW_MASTER_KEY` - 256-bit passphrase for AES-256-GCM encryption and WebSocket HMAC signing
- `HW_DB_HOST`, `HW_DB_USER`, `HW_DB_PASS`, `HW_DB_NAME` - MySQL connection parameters
- `CF_TURNSTILE_SITE_KEY`, `CF_TURNSTILE_SECRET_KEY`, `CF_TURNSTILE_ENABLED` - Cloudflare Turnstile keys
- `HW_TRUSTED_PROXIES` - Comma-separated reverse proxy IPs allowed to supply `HTTP_X_FORWARDED_FOR`

Default database seed accounts (`database/veterinarydb.sql`):
- Pet Owner (`owner`): `user` / `User@1234`
- Staff Veterinarian (`vet_staff`, `PRC-VET-004821`): `vet` / `Vet@1234`
- Head Veterinarian (`head_vet`, `PRC-VET-001001`): `headvet` / `Admin@1234`
