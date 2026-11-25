# HealthWing Shared AI Memory

## Active Architecture & Rules
- Backend: PHP + MySQL on Laragon (VetSystem / HealthWing web repo), hardened through Phase 4.
- Mobile: Standalone Flutter app (C:\laragon\www\healthwing_mobile).
- Strict 3-Role RBAC: owner, vet_staff, head_vet (standalone admin is retired).
- Legal Compliance: RA 10173 (informed consent + consent_log + AES-256-GCM + 365-day owner receipt archive) and RA 9268 (PRC_License_No verification + clinical attribution).
- UI & Code Rules: Zero emojis, no version/update/overhaul wording, loading states labeled "Loading...".

## Ownership Boundaries
- Copilot Fusion (Right): Owns PHP backend (api/mobile/ and docs/MOBILE_API_CONTRACT.md). Never edits C:\laragon\www\healthwing_mobile.
- Antigravity / Neon (Left): Owns Flutter mobile (C:\laragon\www\healthwing_mobile). Reads docs/MOBILE_API_CONTRACT.md as read-only and never edits PHP files.

## Handoff Log
- [Phase 4 Complete] Web backend, DB locks, receipt blockchain, XSS/CSRF fixes, and RA 10173 consent verified on main.
- [2026-11-25] Copilot Fusion built mobile JSON REST bridge in `api/mobile/` (`_bootstrap.php`, `auth.php`, `owner_dashboard.php`, `booking.php`, `receipt.php`, `vet_clinical.php`) and wrote `docs/MOBILE_API_CONTRACT.md`.
  Changed files: `api/mobile/_bootstrap.php`, `api/mobile/auth.php`, `api/mobile/owner_dashboard.php`, `api/mobile/booking.php`, `api/mobile/receipt.php`, `api/mobile/vet_clinical.php`, `docs/MOBILE_API_CONTRACT.md`, `docs/AI_SHARED_MEMORY.md`.
  Contract updates: stateless HMAC-SHA256 Bearer auth (`hw_mobile_issue_token`/`hw_mobile_verify_token`/`hw_mobile_require_role`), login returns token+ws_token+user, register enforces RA 10173 privacy_consent + hw_identity_lock + vet_staff PRC uniqueness + hw_log_consent, owner dashboard/booking/emergency/receipt/vet clinical endpoints documented with request/response/error codes.
  Verification: `php -l` passed for all 6 files in `api/mobile/*.php` after fixing UTF-8 BOM from initial write; no commit made.
