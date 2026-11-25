# HealthWing Cross-Model Audit Protocol & Governance Rules

**Repository:** [Lumfel/HealthWing](https://github.com/Lumfel/HealthWing)  
**Auditors:** Gemini (Independent Security, Privacy, Legal & Final Verification Auditor) + Copilot Fusion (Local Codebase Cross-Audit, Redundancy Removal, Implementation & Verification)  
**Target Role Architecture:** 3-Role Unified Model (`owner`, `vet_staff`, `head_vet` as Super Admin — standalone `admin` retired)

## 1. Non-Negotiable Review Rules (Apply to All Phases)

1. **Read-Only Audit Before Code Edits:** Phase 2 is strictly read-only. Copilot Fusion must write `docs/AUDIT_PHASE2_COPILOT.md` before modifying any application code in Phase 3.
2. **Evidence-Backed Findings Only:** Every finding must include a severity (`CRITICAL`, `HIGH`, `MEDIUM`, `LOW`), exact file path and line numbers, concrete failure mode or statutory citation, and an exact fix specification.
3. **Strict 3-Role Boundary:** Only three roles may exist across SQL, PHP sessions, cookies, WebSockets, and UI:
   - `owner` (Pet Owner / User)
   - `vet_staff` (Veterinarian Staff — Basic Clinical Functions Only)
   - `head_vet` (Head Veterinarian — Super Admin + Full Clinical Access)
   - Any leftover standalone `admin` table, `admin_role` session key, `admin_login.php`, `admin_setup.php`, or `admin/` route is a defect.
4. **Security & Legal Precedence:** If a simplification conflicts with a security, privacy, or legal control, the security/legal control always takes precedence.
5. **Project Hygiene Lock:**
   - Zero emojis anywhere in UI, comments, or documentation.
   - Zero update, changelog, version, or overhaul wording anywhere.
   - All loading indicators must be labeled `Loading...`.
   - Never allow plaintext fallback in `hw_get_encrypted_cookie()` (`enc:gcm:` prefix is mandatory).
   - Never `DELETE` hash headers from `receipt_blocks`; after 365 days, delete only the encrypted owner copy in `archives/owner_receipts/` and mark the block header `Status = 'PURGED_1YR'`.
6. **Verification Gate:** Every touched `.php` file must pass `php -l` with zero syntax errors, and `git status` must contain zero secrets, runtime keys, or encrypted archive files before committing.

## 2. Target 3-Role RBAC Permission Matrix

| Capability / Resource | `owner` (User) | `vet_staff` (Basic Vet) | `head_vet` (Super Admin) |
| :--- | :--- | :--- | :--- |
| Manage own pets & view own Rx | Allowed (own `Owner_ID` only) | View/log for assigned patients | Full clinical access |
| Book/cancel own appointments | Allowed (pending/confirmed only) | View own assigned schedule | Full schedule oversight |
| Emergency triage queue | Submit & track own case | Manage assigned triage cases | Manage & reassign all cases |
| Clinic room occupancy | Read-only availability | Toggle status for active clinical room | Add, edit, and manage all rooms |
| Live WebSocket chat & video | Own authorized booking channel | Assigned booking channels (`text` / `call_event`) | All clinic channels + `system_alert` |
| Blockchain receipts (1-year archive) | View own archived receipts (anti-screenshot) | Issue receipt for assigned visit | Audit chain, view stats, trigger 365-day purge |
| Approve `vet_staff` & verify PRC License | Denied (`403`) | Denied (`403`) | Allowed |
| Security logs, Turnstile console & RA 10173 privacy/erasure requests | Denied (`403`) | Denied (`403`) | Allowed |

## 3. Four-Phase Execution Order (Gemini + Copilot Fusion)

| Phase | Model | Mode | Deliverable / Gate |
| :--- | :--- | :--- | :--- |
| **Phase 1** | **Gemini** | Completed Baseline Audit | Identified 7 security/structural findings on commit `350c5fc` + RA 10173 / RA 9268 compliance baseline (listed below). |
| **Phase 2** | **Copilot Fusion** | Read-Only Cross-Audit | Audits redundancy, dead `admin` files/tables, User-vs-Vet logical confusion, and writes `docs/AUDIT_PHASE2_COPILOT.md`. |
| **Phase 3** | **Copilot Fusion** | Full Patch & Refactor | Implements all Phase 1 security/legal fixes, retires standalone `admin` into `head_vet`/`vet_staff`, cleans redundancy, runs `php -l`, and pushes to `origin main`. |
| **Phase 4** | **Gemini** | Independent Final Audit | Re-inspects the pushed commit on [Lumfel/HealthWing](https://github.com/Lumfel/HealthWing) to confirm zero regressions, zero IDOR/cookie/privacy gaps, and full 3-role compliance. |

## 4. Phase 1 Mandatory Baseline Findings (Already Audited — Must Be Fixed in Phase 3)

1. **`CRITICAL` — `includes/security_crypto.php` (Cookie Forgery):** `hw_get_encrypted_cookie()` must reject any cookie not starting with `enc:gcm:` before calling `hw_decrypt_gcm()`. Remove the `/tmp` master-key fallback (fail closed), move sessions out of webroot, and read the rightmost non-trusted IP in `X-Forwarded-For`.
2. **`HIGH` — `ws_server.php` (WebRTC Broadcast Leak & DoS):** Require positive `$bookingId` and non-null `$ch` with `ws_channel_authorized()` on `webrtc_signal`, cap frame/buffer size at `64 KB`, enforce RFC 6455 client masking, validate `Origin`, and restrict `system_alert` to `head_vet` / `vet_staff`.
3. **`HIGH` — `includes/receipt_blockchain.php` (Chain Break & Race Condition):** Never `DELETE` expired rows from `receipt_blocks`—delete only the owner file in `archives/owner_receipts/` after 365 days and set `Status = 'PURGED_1YR'`. Wrap `hw_receipt_issue()` in a transaction with `SELECT ... FOR UPDATE`.
4. **`HIGH` — `rate_limit.php` (Windows IPv6 Failure):** Replace colons (`:`) in `$safeIp` so IPv6 `::1` on Laragon does not break `fopen()`; sanitize `$action` and return remaining `Retry-After` seconds.
5. **`MEDIUM` — `includes/turnstile.php`, `.htaccess`, `.gitignore`:** Prevent `HTTP_HOST` local-mode bypass, validate Turnstile `hostname`, gate `.css`/`.js` 403 rules with `Sec-Fetch-Dest: document` / empty `Referer` so embedded assets work while direct viewing stays blocked, and add `.healthwing_secrets/` and `.healthwing_hw_master_key` to `.gitignore`.
6. **Legal & Privacy Compliance ([RA 10173](https://privacy.gov.ph/data-privacy-act/) & [IRR](https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/); [RA 9268](https://lawphil.net/statutes/repacts/ra2004/ra_9268_2004.html)):**
   - Capture explicit timestamped Privacy Consent on registration, emergency booking, and uploads (RA 10173 Sec. 12).
   - Enforce strict server-side ownership/role guards (`hw_require_role(...)`) against IDOR (RA 10173 Sec. 25–26).
   - Require `PRC_License_No` + `head_vet` approval for `vet_staff` before diagnosing/prescribing, with attending clinician attribution on every clinical record and receipt (RA 9268).

## 5. Copy-Paste Prompt — Copilot Fusion (Phase 2 Read-Only Cross-Audit)

```text
Read `docs/CROSS_MODEL_AUDIT_PROTOCOL.md` first and follow every rule strictly.
You are executing PHASE 2 (READ-ONLY CROSS-AUDIT). Do NOT edit any application code yet.

Audit the entire HealthWing repository against Section 1–4 of `docs/CROSS_MODEL_AUDIT_PROTOCOL.md` and write your findings to `docs/AUDIT_PHASE2_COPILOT.md`:
1. Privacy & Legal Gap Check (RA 10173 & RA 9268): verify every form, SQL table, API endpoint, and receipt flow for missing consent logging, PII leaks, IDOR vulnerabilities, or missing PRC license attribution.
2. Redundancy & Dead Code Inventory: catalog every obsolete standalone `admin` file (`admin_login.php`, `admin_login_page.php`, `admin_setup.php`, `admin/*`), unused legacy DB table (`admin`, `medical`, `vet`), hardcoded `new mysqli(...)` call, and duplicated session/CSRF/navbar code to remove or merge.
3. Logical Confusion & User-vs-Vet Clarity: trace Booking, Emergency Triage, Calendar, Checkups/Rx, Chat, and Blockchain Receipts to flag invalid state transitions, `vet_staff` vs `head_vet` scope collisions, room-occupancy desyncs, broken links to removed `admin` pages, and internal jargon exposed to Pet Owners.
4. Integration Plan: map exactly how `head_vet` super-admin tabs (Staff Approval + PRC License check, Room Management, Schedule Oversight, Privacy/Traffic Logs) fold cleanly into `vetindex.php` without confusing basic `vet_staff`.

Stop after writing `docs/AUDIT_PHASE2_COPILOT.md` and wait for confirmation before starting Phase 3 implementation.
```

## 6. Copy-Paste Prompt — Copilot Fusion (Phase 3 Full Implementation & Push)

```text
Read `docs/CROSS_MODEL_AUDIT_PROTOCOL.md` and `docs/AUDIT_PHASE2_COPILOT.md` and follow every rule strictly.
You are executing PHASE 3 (FULL IMPLEMENTATION & VERIFICATION):

1. Fix all 5 technical security/bug items in Section 4 (`includes/security_crypto.php`, `ws_server.php`, `includes/receipt_blockchain.php`, `rate_limit.php`, `includes/turnstile.php`, `.htaccess`, `.gitignore`).
2. Implement RA 10173 Privacy Consent capture/logging, strict server-side `hw_require_role(...)` IDOR guards, and RA 9268 `PRC_License_No` + `head_vet` approval for `vet_staff`.
3. Retire standalone `admin` completely (remove obsolete `admin/` pages, `admin_login*`, `admin_setup.php`, and the `admin` table; migrate governance into `head_vet` inside `vetindex.php`).
4. Eliminate all redundant `new mysqli(...)` calls and legacy `medical`/`vet` tables, resolve all User-vs-Vet logical/terminology confusions from Phase 2, and enforce zero emojis, zero update/version wording, and `Loading...` labels.
5. Update `docs/SECURE_DOCUMENTATION.md` with the 3-role sample accounts (`user`, `vet_staff`, `head_vet`), run `php -l` across every `.php` file, verify `git status` has zero secrets, commit cleanly, and push to `origin main` for Phase 4 independent verification by Gemini.
```