# HealthWing Veterinary Clinic System - Security & Architecture Documentation

## 1. Directory Structure

```text
VetSystem/
├── admin/                  Admin dashboard pages, auth guard, and reporting endpoints
├── api/                    JSON APIs, asset delivery proxy, and receipt ledger API
├── archives/               Restricted archive storage (blocked from direct HTTP access)
│   └── owner_receipts/     AES-256-GCM encrypted 365-day owner receipt copies
├── assets/
│   ├── css/                Application stylesheets (CS.css, pen.css, hero-glass.css, loader.css)
│   ├── icons/              Clinical service SVG icons
│   ├── images/             Clinic photography and UI branding assets
│   └── js/                 Frontend scripts (JS.js, script.js, call.js, is.js, glass-shader.js, loader.js)
├── database/               Database schema and seed SQL files
├── docs/                   Documentation and architectural references
├── emergency_uploads/      Emergency case photo uploads (PHP execution disabled)
├── includes/               Core shared engines (db, security_crypto, scheduling_engine, receipt_blockchain, navbar, turnstile)
├── pet_photos/             Pet profile photo uploads (PHP execution disabled)
├── rate_limit_data/        Sliding-window rate-limit logs and encrypted cache/sessions (HTTP denied)
├── vendor/                 Self-hosted frontend dependencies (Tailwind, Flowbite, Swiper, Chart.js, Motion, Alpine)
├── login.php               Unified sign-in portal
├── register.php            Pet owner registration portal
├── register_vet.php        Veterinarian registration request portal
├── choose_role.php         Account role selection page
├── admin_login_page.php    Clinic administrator sign-in page
├── services.php            Consolidated clinical services hub
├── receipts.php            Protected clinic receipts and blockchain proof viewer
└── ws_server.php           Authenticated RFC 6455 WebSocket server
```

## 2. Security Controls

### 2.1 AES-256-GCM Encryption & Master Key Isolation
- All session cookies (`hw_auth_gcm`, `hw_user_gcm`, `hw_admin_gcm`, `hw_cf_clearance_gcm`), medical checkup fields, pet records, server caches, and owner receipt archives are encrypted with `aes-256-gcm` (`96-bit` CSPRNG IV and `128-bit` authentication tag).
- `hw_get_master_key()` resolves `HW_MASTER_KEY` from the environment first and otherwise persists the random 256-bit key outside the document root (`.healthwing_secrets/.hw_master_key` in the parent of the webroot, with system temp directory fallback).
- Encryption fails closed: `hw_encrypt_gcm()` throws a `RuntimeException` if OpenSSL AES-256-GCM is unavailable rather than falling back to plaintext.
- Persistent sliding-window cookies use a 90-day TTL with `HttpOnly`, `SameSite=Lax`, and automatic `Secure` flag when HTTPS or `X-Forwarded-Proto: https` is active.

### 2.2 Session-Bound CSRF Protection
- `includes/security_crypto.php` provides `hw_csrf_token()`, `hw_csrf_verify()`, and `hw_require_csrf()`.
- Every page embeds `<meta name="hw-csrf-token">`, HTML POST forms carry a hidden `csrf_token` field, and `assets/js/loader.js` automatically injects `X-CSRF-Token` into state-changing `fetch()` requests.

### 2.3 WebSocket HMAC-SHA256 Authentication
- `api/chat_api.php` issues short-lived HMAC-SHA256 tokens (`hw_generate_ws_token`) encoding the verified server-side role, user ID, display name, allowed channels, and expiration timestamp.
- `ws_server.php` ignores any client-supplied role/identity attributes and verifies the signed token via `hw_verify_ws_token()` before permitting subscriptions, chat broadcasts, WebRTC signaling, or triage updates.

### 2.4 Cloudflare Turnstile & Trusted-Proxy Rate Limiting
- `includes/turnstile.php` restricts Cloudflare Always-Pass test keys and offline fail-open strictly to verified localhost requests (`hw_is_local_request()`).
- Pre-clearance cookies (`hw_cf_clearance_gcm`) validate both client IP (`hw_get_client_ip()`) and challenge action before bypassing Turnstile verification.
- The interactive Turnstile diagnostic console on `turnstile.php` is restricted to administrators in non-local environments.
- `hw_get_client_ip()` and `rate_limit.php` strictly use `REMOTE_ADDR`, honoring `HTTP_X_FORWARDED_FOR` only when `REMOTE_ADDR` matches an explicit `HW_TRUSTED_PROXIES` allowlist.

### 2.5 Centralized Database, File Uploads & Apache Hardening
- All database access flows through `hw_get_db()` in `includes/db.php` with generic error messages to avoid leaking credentials or internal diagnostics.
- Uploaded images in `upload_pet_photo.php` and `submit_emergency.php` are verified with `finfo_file(FILEINFO_MIME_TYPE)` and `getimagesize()`, renamed randomly, and stored in folders where PHP execution is disabled (`php_flag engine off`).
- Root `.htaccess` applies `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, and HSTS (`HTTPS` and `X-Forwarded-Proto: https`).
- Direct browser access (`403 Forbidden`) blocks `.html`, `.htm`, `.css`, `.js`, `.map`, `.sql`, `.json`, `.gcm`, `.key`, `.md`, `.bat`, `.sh`, `.env`, `.git`, `composer.*`, and `.hw_master_key`. Embedded stylesheets and scripts are served exclusively through `api/asset_proxy.php`.
- `archives/`, `docs/`, `includes/`, `database/`, and `rate_limit_data/` deny all direct HTTP requests via `.htaccess`.

## 3. Receipt Blockchain, 1-Year Encrypted Archive & Anti-Screenshot Viewer
- Completed appointments (`update_sched_status.php`), completed emergencies (`update_status.php`, `admin/update_emergency_status.php`), and staff-issued receipts (`api/receipt_api.php`) mint an immutable block in `receipt_blocks`.
- Each block computes a SHA-256 `Merkle_Root` over the receipt line items and metadata, links to the previous block (`Prev_Hash`), and computes a chained `Block_Hash`.
- An AES-256-GCM encrypted owner copy is written to `archives/owner_receipts/<Receipt_Code>.gcm` with a strict 365-day (`Expires_At`) retention limit; expired files and references are automatically purged by `hw_receipt_purge_expired()`.
- `receipts.php` enforces anti-screenshot controls: focus-blur and privacy veil on `blur` / `visibilitychange` / `PrintScreen` and capture shortcuts, clipboard clearing attempts, disabled context menu / selection / drag / copy, dynamic user watermarks, and `@media print` blackout.

## 4. Environment Configuration & Seed Accounts

Supported optional environment variables for production deployments:
- `HW_MASTER_KEY` - 256-bit passphrase for AES-256-GCM encryption and WebSocket HMAC signing
- `HW_DB_HOST`, `HW_DB_USER`, `HW_DB_PASS`, `HW_DB_NAME` - MySQL connection parameters
- `CF_TURNSTILE_SITE_KEY`, `CF_TURNSTILE_SECRET_KEY`, `CF_TURNSTILE_ENABLED` - Cloudflare Turnstile keys
- `HW_TRUSTED_PROXIES` - Comma-separated reverse proxy IPs allowed to supply `HTTP_X_FORWARDED_FOR`

Default database seed accounts (`database/veterinarydb.sql`):
- Pet Owner: `user` / `User@1234`
- Veterinarian: `vet` / `Vet@1234`
- Administrator: `admin` / `Admin@1234`
