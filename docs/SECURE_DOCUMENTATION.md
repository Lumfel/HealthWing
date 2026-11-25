# HealthWing / VetSystem Secure Documentation

## Architecture Overview
- **Platform:** HealthWing Veterinary Clinic and Triage System (PHP 8, MySQL, Apache/Laragon, WebSockets).
- **Strict 3-Role Model:**
  - `owner` (Pet Owner): books appointments/emergency triage, manages own pets, views own receipts and records, participates in own booking channels.
  - `vet_staff` (Veterinarian Staff): handles clinical workflows, assigned bookings, checkups, prescriptions, receipts, and authorized patient channels.
  - `head_vet` (Head Veterinarian / Super Admin): all `vet_staff` capabilities plus governance - staff approval and PRC license verification, room management, schedule oversight, privacy/traffic logs, and administrative controls. There is no standalone `admin` role.

## Sample Accounts (Development/Seed)
These accounts are seeded by `includes/db.php` when tables are empty. Change credentials immediately outside local development.

| Role | Username | Email | Notes |
|---|---|---|---|
| owner (`user`) | `user` | `user@healthwing.local` | Pet owner account for booking, pets, receipts. |
| vet_staff | `vet` | `vet@healthwing.local` | Clinical staff account; requires approved status and PRC license. |
| head_vet | `headvet` | `headvet@healthwing.local` | Head veterinarian with governance/super-admin controls. |

## Security Baseline
- **Encryption:** AES-256-GCM for sensitive fields and encrypted cookies. Cookie decryption requires `enc:gcm:` prefix; non-GCM/legacy plaintext cookies are rejected.
- **Master Key:** Resolved from `HW_MASTER_KEY` or `.healthwing_secrets/.hw_master_key` outside webroot. The application fails closed if the key cannot be resolved/persisted securely; no `/tmp` fallback.
- **CSRF:** All state-changing requests require CSRF validation (`hw_require_csrf()` / session token).
- **RBAC/IDOR:** Server-side role enforcement via `hw_require_role(...)` and role helpers (`hw_is_owner`, `hw_is_vet_staff`, `hw_is_head_vet`, `hw_is_vet_role`). Sensitive endpoints scope records by authenticated owner/vet identity.
- **Rate Limiting:** `rate_limit.php` uses `hw_get_client_ip()`, sanitizes action names, uses Windows-safe IP filenames, and returns remaining `Retry-After` seconds.
- **Turnstile:** Cloudflare Turnstile verification includes localhost hardening, hostname validation, and head-vet-gated diagnostic console. Local fail-open behavior is restricted to verified local requests.
- **WebSocket Security:** `ws_server.php` enforces Origin validation, RFC 6455 client masking, 64KB frame/buffer cap, authenticated token claims, channel authorization, positive `booking_id` authorization for `webrtc_signal`, and vet-only `system_alert`/triage broadcasts.
- **Receipt Blockchain:** Receipt issuance is transactional and serialized; chain headers (`Receipt_Code`, `Merkle_Root`, `Prev_Hash`, `Block_Hash`) are never deleted. After 365 days, only the encrypted owner archive copy in `archives/owner_receipts/` is purged and the block is marked `Status='PURGED_1YR'`.
- **XFF Trust:** `hw_get_client_ip()` uses `REMOTE_ADDR` unless `REMOTE_ADDR` is in explicit trusted proxy list; XFF parsing selects the rightmost non-trusted candidate.

## RA 10173 Privacy Controls
- **Consent Logging:** `consent_log` records timestamped consent/action events for registration, emergency intake, uploads, contact, and other privacy-relevant flows. `audit_log` records administrative/privacy-sensitive actions.
- **Data Minimization:** Owner-facing views expose only necessary data. PII fields are encrypted where applicable.
- **Retention:** Encrypted receipt owner copies are retained for 365 days; after purge, chain integrity headers remain and status becomes `PURGED_1YR`.
- **Access Control:** Owner records/receipts are scoped by authenticated owner identity; vet access is limited to clinical roles and authorized workflows.

## RA 9268 / Clinical Attribution
- **PRC License:** Veterinarian records include PRC license fields and verification status. `vet_staff` accounts require head-vet approval and verified PRC license before clinical issuance.
- **Attending Clinician Attribution:** Clinical receipts and checkup records carry attending clinician identity and PRC license number. Receipt issuance rejects missing PRC attribution.
- **Head Vet Governance:** Head veterinarian manages staff approval, PRC verification, room management, schedule oversight, and privacy/traffic logs from `vetindex.php`.

## Retired Standalone Admin
- `admin_login.php`, `admin_login_page.php`, `admin_setup.php`, and `admin/*` standalone admin surfaces are retired. Governance is handled by `head_vet` inside `vetindex.php`.
- Legacy `admin`, `medical`, and `vet` tables are removed from canonical schema and treated as dead schema.

## Verification Checklist
- Run `php -l` on every `.php` file.
- Confirm `git status` contains no secrets, runtime keys, encrypted archive files, or rate-limit runtime artifacts.
- Confirm no standalone admin role/path remains active.
- Confirm sensitive endpoints enforce `hw_require_role(...)` and owner/vet scoping.
- Confirm receipts retain hash headers and mark `PURGED_1YR` after archive purge.
