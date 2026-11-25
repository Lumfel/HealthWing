# Defensive Database Red-Team Audit — HealthWing / VetSystem

**Scope:** Read-only audit of `database/veterinarydb.sql`, `includes/db.php`, `includes/receipt_blockchain.php`, `includes/scheduling_engine.php`, and PHP/Node.js files that query MySQL.  
**Date:** 2026-11-25  
**Method:** Static code review + schema/query cross-reference. No code was modified.  
**Note:** This report is defensive. Findings describe risk and exact remediation; it does not include exploit payloads.

## Executive Summary

The highest-risk database issues are **booking/triage race conditions with no transactional locking**, **schema drift between `veterinarydb.sql` / `db.php` / runtime code**, **destructive cascade FKs that can erase clinical/history data**, and **role-model drift** (no `vet_staff`/`head_vet`/`PRC_License_No` model in schema despite application assumptions). Query parameterization is mostly prepared-statement based, but there are still raw/interpolated SQL patterns and error-handling gaps that should be hardened.

---

## 1. Query Safety & Parameterization

### HIGH — Interpolated SQL in schema/bootstrap and runtime paths

**Files / lines**
- `includes/db.php:43-55,66-117` — seed checks/inserts and `receipt_blocks` DDL use `$conn->query(...)` with static strings; default room insert builds SQL with `$conn->real_escape_string(...)` and string interpolation (`includes/db.php:149-157`).
- `includes/scheduling_engine.php:184-223,231-263` — availability checks append `AND Schedule_ID != ". (int)$ignoreScheduleId` directly into SQL strings before `prepare()`. Values are cast to int, reducing injection risk, but this is still string-built SQL and bypasses uniform parameterization.
- `admin/update_emergency_status.php:39`, `update_status.php:57` — `DELETE FROM emergency_booking WHERE Resolved_At IS NOT NULL AND Resolved_At < (NOW() - INTERVAL 1 DAY)` is raw query (static, low injection risk, but unprepared and repeated in multiple endpoints).
- Multiple files use `$conn->query(...)` for static operational queries (e.g., `includes/scheduling_engine.php:535,556,599,627`, `api/scheduler.php:55-76`, `receipts.php:21`).

**Root cause**
The codebase mixes prepared statements with raw `query()`/string-built SQL. Integer casts mitigate some cases, but there is no single query-safety rule enforcing parameterized statements for all data-influenced queries.

**Defensive fix**
- Replace string-built SQL with prepared statements everywhere, including integer IDs: `AND Schedule_ID != ?` with `bind_param`.
- Centralize DB access behind helpers that reject non-parameterized dynamic SQL except audited static migrations.
- Add a CI grep gate failing on `->query("...$` / `->query('...$` / concatenated SQL except allowlisted static DDL/migration files.
- Keep `real_escape_string` only as defense-in-depth, never as primary protection.

### MEDIUM — Dynamic identifier / IN-clause / ORDER BY review

**Files / lines**
- Searched for dynamic `ORDER BY $`, `LIMIT $`, `IN ($...)` patterns: no direct matches found by static grep.
- `includes/scheduling_engine.php:269-427` builds slot search in application logic; no dynamic column names from user input observed in reviewed paths.

**Root cause**
Current reviewed query paths mostly use fixed column names and fixed ORDER BY clauses.

**Defensive fix**
- Preserve this property. If sorting/filtering is added, use allowlists mapping request keys to column identifiers; never interpolate identifiers from request input.
- For dynamic `IN (...)`, build parameter placeholders (`?, ?, ...`) and bind typed values; never join raw IDs into SQL.

### LOW — `$conn->error` / `connect_error` leakage

**Files / lines**
- `includes/db.php:20-23` catches connection failure and throws generic `RuntimeException('Database connection unavailable.')`; it does **not** expose `$conn->connect_error`. This is good.
- Broad grep for `->error`, `connect_error`, `mysqli_error` returned only `includes/db.php:20`.
- API endpoints generally return generic JSON errors (`submit_emergency.php:27-31`, `owner_add_pet.php:62-64`, `update_sched_status.php:112-114`, `api/receipt_api.php:164-167`).

**Root cause**
Error handling is mostly generic, but there is no centralized error handler guaranteeing DB errors are never logged/returned in future changes.

**Defensive fix**
- Add a central DB error logger that writes `$conn->error`/exception details to server-side logs only, and returns opaque error IDs/messages to clients.
- Add tests/static checks forbidding `echo/json_encode` of `$conn->error`, `$e->getMessage()` from DB exceptions in production paths.

---

## 2. Concurrency, Race Conditions & Transaction Integrity

### CRITICAL — Appointment booking has check-then-insert race (double booking)

**Files / lines**
- `submit_booking.php:95-133` — resolves owner/pet, calls `VetSchedulingEngine::findOptimalSlotAndResources()`, then inserts into `schedule` with no transaction and no lock.
- `includes/scheduling_engine.php:179-220` (`isVetAvailable`), `222-263` (`isRoomAvailable`) — availability checks are plain `SELECT COUNT(*)` queries; no `FOR UPDATE`, no advisory lock, no transaction.
- `includes/scheduling_engine.php:266-427` (`findOptimalSlotAndResources`) — scans slots and returns selected vet/room/time based on non-locked reads.
- `api/scheduler.php:21-31` exposes preview endpoint with same non-locking computation.

**Root cause**
Availability is checked and later written in separate non-atomic operations. Two concurrent requests can select the same vet/room/slot before either insert commits.

**Defensive fix**
- Wrap booking in `BEGIN IMMEDIATE`/InnoDB transaction with appropriate isolation.
- Lock candidate resources before final insert: `SELECT ... FOR UPDATE` on veterinarian/room rows in a deterministic order, then re-check overlap with `SELECT ... FOR UPDATE`/locking reads against conflicting `schedule` rows.
- Add DB-level exclusion guard where possible: generated slot key + unique constraint for active appointments per vet and per room, or a booking lock table with unique `(resource_type, resource_id, slot_start)`.
- Revalidate availability immediately before `INSERT`; on conflict, retry slot search.

### CRITICAL — Emergency triage recalculation is non-transactional and race-prone

**Files / lines**
- `submit_emergency.php:124-160` — inserts emergency booking, then calls `$engine->recalculateEmergencyTriage()`; no transaction around insert+triage.
- `includes/scheduling_engine.php:528-841` (`recalculateEmergencyTriage`) — reads active cases, computes scores, resets room pointers (`$this->conn->query("UPDATE room SET Current_Emergency_ID = NULL, Status = 'idle' WHERE Current_Emergency_ID IS NOT NULL")` at `599`), updates rooms/schedules/emergency rows in loops with no transaction/locks.
- `update_status.php:108-110`, `admin/update_emergency_status.php:92-94` — status updates commit then trigger triage recalculation outside a shared lock.

**Root cause**
Triage is a multi-row read/write state machine without atomicity. Concurrent submissions/status changes can produce stale queue positions, double room/vet assignment, lost preemption notes, or inconsistent room pointers.

**Defensive fix**
- Run triage inside a transaction and acquire a deterministic lock (e.g., `SELECT ... FOR UPDATE` on a singleton `triage_state` row, or advisory lock `GET_LOCK('hw_triage', timeout)`).
- Lock active `emergency_booking`, affected `room`, and overlapping `schedule` rows before mutation.
- Make recalculation idempotent and versioned (`triage_version`/`updated_at` checks) to detect concurrent writers.
- Move room reset + assignment updates into the same transaction; commit only after all writes succeed.

### HIGH — Receipt blockchain genesis/concurrent issuance can collide or duplicate by reference

**Files / lines**
- `includes/receipt_blockchain.php:170-260` (`hw_receipt_issue`) — uses `begin_transaction()`, checks existing reference with `SELECT ... FOR UPDATE` (`181-190`), and locks last block with `SELECT ... FROM receipt_blocks ORDER BY Block_Index DESC LIMIT 1 FOR UPDATE` (`193-197`).
- `database/veterinarydb.sql:333-360` — `receipt_blocks` has `UNIQUE KEY uniq_receipt_code`, `UNIQUE KEY uniq_block_index`, `KEY idx_ref (Reference_Type, Reference_ID)`, but **no unique constraint on `(Reference_Type, Reference_ID)`**.
- `includes/db.php:66-87` creates `receipt_blocks` without `Status` / attending clinician columns; runtime DDL in `receipt_blockchain.php:45-55` adds them.

**Root cause**
For the first block, there is no existing row to lock with `ORDER BY Block_Index DESC ... FOR UPDATE`, so two concurrent genesis issuances can race. For non-manual references, duplicate prevention relies on a read check; without a unique `(Reference_Type, Reference_ID)` constraint, concurrent inserts can both succeed.

**Defensive fix**
- Add `UNIQUE KEY uniq_receipt_ref (Reference_Type, Reference_ID)` for non-manual references (or a generated/stored key handling manual null refs appropriately).
- Use a serializable lock for block append: lock a dedicated ledger mutex row (`SELECT ... FOR UPDATE`) or use advisory lock around index assignment.
- Handle duplicate-key exceptions by returning the existing receipt (idempotent issue).
- Keep hash/merkle computation inside the same transaction and commit only after successful insert.

### MEDIUM — Receipt purge is compliant on row retention but has edge-case integrity gaps

**Files / lines**
- `includes/receipt_blockchain.php:121-151` (`hw_receipt_purge_expired`) — selects expired rows where `Status <> 'PURGED_1YR'`, unlinks archive file, sets `Archive_Filename = NULL, Status = 'PURGED_1YR'`; it does **not** delete `receipt_blocks` rows. This matches the stated requirement.
- `includes/receipt_blockchain.php:143-149` — additionally unlinks any `*.gcm` files older than cutoff by filesystem mtime, regardless of DB row state.

**Root cause**
File cleanup is partially filesystem-driven and silent on unlink failures; DB status may be set to purged even when file deletion fails.

**Defensive fix**
- Make purge transactional/auditable: record purge attempt timestamp, result, and error.
- Only unlink files that correspond to DB rows selected for purge, or verify filename-to-row mapping before mtime-based deletion.
- Do not mark `PURGED_1YR` until unlink succeeds or after a verified retry/quarantine flow.
- Keep `receipt_blocks` rows immutable; never delete ledger rows.

### MEDIUM — Room occupancy desync on cancellation/preemption/completion

**Files / lines**
- `update_status.php:76-82` — releases room on emergency resolved by setting `Status='idle', Current_Emergency_ID=NULL` where `Current_Emergency_ID=?`.
- `admin/update_emergency_status.php:60-66` — same pattern.
- `update_sched_status.php:77-99` — owner cancellation sets schedule `Cancelled` but **does not release room** (`room.Current_Schedule_ID`/`Status`) before returning. Staff/admin path releases room only for `Completed/Cancelled` (`91-99`).
- `includes/scheduling_engine.php:599,721-838` — triage resets all emergency room pointers, then reassigns; preemption moves schedule but does not explicitly reconcile old room occupancy outside triage pass.

**Root cause**
Room state is updated in multiple endpoints with different rules; no single invariant-enforcing transaction/state machine.

**Defensive fix**
- Centralize room state transitions in one service/function with transaction and row locks.
- On any schedule cancel/complete/preempt, release room only when `Current_Schedule_ID` matches and no active emergency owns the room.
- Add periodic reconciliation job: derive room occupancy from active `emergency_booking`/`schedule` rows and repair mismatches.
- Add FK/constraint strategy or trigger-guarded checks for `room.Current_Emergency_ID`/`Current_Schedule_ID` validity.

---

## 3. Schema Drift, Foreign Keys & Cascade Bugs

### CRITICAL — Destructive cascades can erase clinical/history data

**Files / lines**
- `database/veterinarydb.sql:157-158` — `pet_checkup_ibfk_1` `FOREIGN KEY (Pet_ID) REFERENCES pet(Pet_ID) ON DELETE CASCADE`.
- `database/veterinarydb.sql:227-228` — `schedule_ibfk_1` owner CASCADE, `schedule_ibfk_2` pet CASCADE.
- `database/veterinarydb.sql:268` — `emergency_booking_ibfk_1` owner CASCADE.
- `database/veterinarydb.sql:157` means deleting an owner cascades to pets, then to pet_checkups and schedules; this can silently wipe clinical history and historical schedules.

**Root cause**
Schema uses convenience cascades for core clinical/historical entities.

**Defensive fix**
- For clinical/history tables, use `ON DELETE RESTRICT` or `ON DELETE SET NULL` with soft-delete/anonymization.
- Never physically delete owners/pets with clinical history; implement `is_deleted`/`anonymized_at` and retention workflow.
- For `pet_checkup`, preserve records even if pet is archived; keep immutable clinician/audit fields.
- Add migration to replace destructive FKs after data backfill.

### HIGH — Schema drift between SQL schema, db.php bootstrap, and runtime code

**Files / lines**
- `database/veterinarydb.sql:333-360` defines `receipt_blocks` without `Attending_Clinician_Name`, `Attending_Clinician_PRC`, `Status`.
- `includes/db.php:66-87` creates `receipt_blocks` without those columns.
- `includes/receipt_blockchain.php:23-55,241-259` expects/adds `Status`, `Attending_Clinician_Name`, `Attending_Clinician_PRC` and writes them.
- `database/veterinarydb.sql` includes retired/legacy tables `admin` (`27-39`), `medical` (`122-135`), `vet` (`181-195`); prompt flags these as retired references to audit.
- `includes/db.php:43-55` seeds/admin-bootstraps `admin`, `owner`, `veterinarian` directly.

**Root cause**
Multiple schema sources of truth (SQL dump, runtime ensure_schema, feature-specific ensure_table) have diverged.

**Defensive fix**
- Establish one canonical migration path (versioned migrations). Make `veterinarydb.sql` generated from migrations or keep them in lockstep.
- Remove runtime DDL from request path except guarded installer/migration command.
- Add schema diff check in CI comparing expected columns/indexes/FKs to actual DB.
- Decide whether `admin/medical/vet` are retired; if retired, remove from schema/bootstrap/code and migrate data.

### HIGH — Missing/insufficient constraints for role model and clinical accountability

**Files / lines**
- `database/veterinarydb.sql:70-87` — `veterinarian` has no `PRC_License_No`, no `Role_Level` (`head_vet`/`vet_staff`), no constraint enforcing Super Admin boundary.
- `database/veterinarydb.sql:161-179` — `pet_checkup` has `Veterinarian_ID` nullable, no attending clinician PRC/license field, no immutable audit of attending clinician identity.
- `includes/receipt_blockchain.php:203-206,241-259` stores `Attending_Clinician_Name/PRC` in receipts, but schema/base tables do not enforce clinician credential presence.

**Root cause**
Data model does not encode the stated 3-role and clinical accountability requirements.

**Defensive fix**
- Add `veterinarian.PRC_License_No` (unique, not null where applicable), `veterinarian.Role_Level` enum/check (`head_vet`,`vet_staff`), and status/approval constraints.
- Add `pet_checkup.Attending_Veterinarian_ID` NOT NULL (or equivalent), `Attending_PRC_License_No` snapshot, and immutable created/updated audit fields.
- Enforce role checks server-side for all vet/admin endpoints; do not infer privilege from session role string alone.
- Backfill existing rows before adding NOT NULL constraints.

### MEDIUM — Missing composite index for chat hot query

**Files / lines**
- `database/veterinarydb.sql:303-331` — `chat_messages` has separate keys: `KEY Channel_Key (Channel_Key)` and `KEY Created_At (Created_At)`, but no composite `(Channel_Key, Created_At)` / `(Channel_Key, Message_ID)`.
- `api/chat_api.php:211-229` — history query filters by `Channel_Key = ? AND Message_ID > ? ORDER BY Message_ID ASC LIMIT 200`.

**Root cause**
Indexing does not match the primary chat history access pattern.

**Defensive fix**
- Add `KEY idx_chat_channel_msg (Channel_Key, Message_ID)` and/or `KEY idx_chat_channel_created (Channel_Key, Created_At)`.
- Verify query plans with `EXPLAIN` under realistic data volume.

### MEDIUM — Missing FK constraints for operational references

**Files / lines**
- `database/veterinarydb.sql:234-269` — `emergency_booking` has `Assigned_Vet_ID`, `Assigned_Room_ID`, `Preempted_Schedule_ID`, `Pet_ID` columns but no foreign keys.
- `database/veterinarydb.sql:271-285` — `ratings` has no FKs for `Owner_ID`, `Schedule_ID`.
- `database/veterinarydb.sql:303-331` — `chat_messages` has no FKs for `Booking_ID`, `Schedule_ID`, `Sender_ID` (polymorphic sender complicates FK).
- `database/veterinarydb.sql:286-302` — `call_session`/`call_ice_candidate` lack FK constraints in shown schema.

**Root cause**
Schema favors flexibility but allows orphaned references and inconsistent state.

**Defensive fix**
- Add FKs where relationships are stable (`emergency_booking.Pet_ID`, `Assigned_Vet_ID`, `Assigned_Room_ID`, `Preempted_Schedule_ID`; `ratings.Owner_ID/Schedule_ID`; call/chat booking/schedule refs).
- For polymorphic sender IDs, enforce integrity in application/service layer and add CHECK constraints or separate sender columns/tables.
- Add cleanup/reconciliation migration for existing orphans before adding constraints.

---

## 4. Object-Level Authorization (IDOR) & 3-Role Boundaries

### HIGH — Role boundary drift: no `vet_staff` / `head_vet` model, coarse vet/admin checks

**Files / lines**
- `database/veterinarydb.sql:70-87` — veterinarian table lacks `Role_Level`.
- `update_status.php:9-16` and `update_sched_status.php:9-16` allow any authenticated Veterinarian or Admin; no distinction between `vet_staff` and `head_vet`.
- `api/scheduler.php:82-90` allows any vet/admin to auto-assign all unassigned schedules.
- `admin/auth_guard.php` / admin endpoints rely on `admin_role` session; separate from veterinarian role model.

**Root cause**
The implemented role model (owner/veterinarian/admin session flags) does not match the required 3-role boundary (`owner`, `vet_staff`, `head_vet` Super Admin).

**Defensive fix**
- Model roles explicitly in DB: owners, veterinarians with `Role_Level` (`vet_staff`,`head_vet`), and remove/merge legacy admin semantics as required.
- Enforce server-side RBAC per endpoint/action matrix; `head_vet` only for privileged actions (staff management, override, destructive admin ops).
- Add authorization middleware/helpers and deny-by-default.

### MEDIUM — IDOR protections present in several owner endpoints, but coverage is inconsistent

**Files / lines (good patterns)**
- `api/receipt_api.php:76-92,105-121` — owners scoped by `Owner_ID` and expiry; view uses `hw_receipt_read_for_viewer`.
- `api/chat_api.php:57-83,175-182,246-252` — owner channel allowlist enforces booking/schedule ownership.
- `get_my_pets.php:18-59` — owner-scoped by session owner.
- `owner_bookings.php:35-67` — owner-scoped queries.
- `cancel_emergency.php:48-68` — verifies booking belongs to owner.
- `upload_pet_photo.php:41-56` — verifies pet ownership.
- `update_sched_status.php:45-67` — owner can only cancel own schedule.

**Files / lines (risks/gaps)**
- `api/scheduler.php:55-76` `resources` action returns owners, pets, vets, rooms to any caller with no auth check shown; this can expose PII/operational data.
- `api/vet_api.php` and admin manage endpoints need full RBAC/ownership review; grep indicates broad data access patterns. At minimum, ensure every `SELECT/UPDATE/DELETE` on target tables enforces role and ownership/assignment scope.
- `receipts.php` page loads owner list for staff (`21-29`) — acceptable for staff, but ensure no owner-side leakage and CSRF-protected actions.

**Root cause**
Authorization is implemented per endpoint, not centrally enforced.

**Defensive fix**
- Create centralized policy checks: `require_owner_owns_pet($petId)`, `require_owner_owns_schedule($scheduleId)`, `require_vet_role($level)`.
- Add endpoint inventory tests for IDOR on all target tables (`pet`, `pet_checkup`, `schedule`, `emergency_booking`, `chat_messages`, `receipt_blocks`).
- Deny access by default when role/ownership cannot be proven.

### HIGH — Cross-table username/email uniqueness incomplete across `admin`

**Files / lines**
- `signup.php:41-55` checks uniqueness across `owner` and `veterinarian` only; no `admin` table check.
- `database/veterinarydb.sql:27-39` defines `admin` with unique username/email; `includes/db.php:43-47` seeds default admin.
- `log.php` / admin login flows (not fully reviewed here) may authenticate against `admin` separately.

**Root cause**
Account uniqueness is enforced for owner/vet but not against admin, allowing identity collisions across role stores.

**Defensive fix**
- Enforce global identity uniqueness across all auth principals (`owner`, `veterinarian`, `admin`/role table) via a canonical `accounts` table or cross-table unique guard.
- Add DB-level uniqueness strategy: single identity table with unique email/username, or triggers/pre-insert checks in transaction.
- Normalize emails/usernames case-insensitively (`LOWER(email)`) and apply consistent collation/constraints.

---

## 5. Data Encryption, PII & Privacy Compliance (RA 10173 / RA 9268)

### HIGH — Sensitive PII/clinical data stored plaintext

**Files / lines**
- `database/veterinarydb.sql:45-67` owner: `First_Name`, `Last_Name`, `Age`, `Address`, `Contact`, `email`, `username` plaintext.
- `database/veterinarydb.sql:70-87` veterinarian: profile/contact/specialties plaintext; no `PRC_License_No`.
- `database/veterinarydb.sql:234-269` emergency_booking: `Name`, `Email`, `Phone_Number`, `Additional_Details`, `Image_Path`, `IP_Address` plaintext.
- `database/veterinarydb.sql:161-179` pet_checkup: `Notes`, `Diagnosis`, `Prescription` plaintext.
- `database/veterinarydb.sql:303-331` chat_messages: `Message_Text` plaintext.
- `database/veterinarydb.sql:286-302` traffic_log: `IP_Address` plaintext, no retention/consent fields.
- Encryption observed for some pet fields: `owner_add_pet.php:35-36` encrypts Description/Past_Conditions; `get_my_pets.php:60-67` decrypts; receipts payload encrypted in `receipt_blockchain.php:230`.

**Root cause**
Encryption is applied selectively (pet fields, receipt payload) but most PII/clinical fields remain plaintext.

**Defensive fix**
- Classify data by sensitivity (PII, health, credentials, audit). Encrypt sensitive-at-rest fields with AES-256-GCM and purpose-specific AAD.
- Store blind indexes/hashes for searchable encrypted fields (email/phone) where needed.
- Avoid storing raw IPs longer than necessary; pseudonymize/truncate and define retention.
- Add consent timestamp/purpose/legal basis fields for emergency/contact flows and chat/teleconsult features; document RA 10173 processing basis and RA 9268 clinical record controls.

### HIGH — Ciphertext storage/column-length and key-management risks

**Files / lines**
- `includes/security_crypto.php:20-55` master key derived from env or generated file outside webroot in `.healthwing_secrets`; file permissions set best-effort. Good baseline, but key rotation and KMS/HSM are absent.
- `includes/security_crypto.php:61-90` uses AES-256-GCM with random IV and tag; format `enc:gcm:<base64(iv.tag.ciphertext)>`. Good.
- `database/veterinarydb.sql:146-156` pet `Description/Past_Conditions/Current_Ailment/Current_Medications` are `TEXT` — acceptable for ciphertext expansion; but other future encrypted fields may be placed in short `VARCHAR` columns.
- `database/veterinarydb.sql:234-269` emergency booking PII columns are short `VARCHAR`s; encrypting them later without migration will truncate ciphertext.

**Root cause**
No schema-wide policy ensures encrypted fields use sufficient length/type and key lifecycle management.

**Defensive fix**
- For any encrypted column, use `TEXT`/`MEDIUMTEXT` or sized VARBINARY/TEXT with calculated overhead; add migration checks preventing truncation.
- Add key versioning (`enc:gcm:v1:`), rotation, and re-encrypt jobs; keep master key in managed secret store.
- Add decrypt-failure monitoring and avoid returning plaintext fallback silently except for legacy migration windows with audit.

### MEDIUM — Traffic log and emergency data retention/consent not fully defined

**Files / lines**
- `database/veterinarydb.sql:286-302` `traffic_log` has timestamp but no retention window/consent metadata.
- `rate_limit.php:7-21` logs IP/action/status on rate-limit events.
- `update_status.php:57`, `admin/update_emergency_status.php:39` delete resolved emergency bookings after 1 day — aggressive deletion may conflict with clinical/legal retention; also deletes before ensuring receipts/audit trails are sufficient.

**Root cause**
Retention is implemented ad hoc and not aligned with privacy/clinical record requirements.

**Defensive fix**
- Define retention matrix by data class: security logs, emergency records, clinical records, receipts, chat.
- Replace hard deletes with retention/anonymization jobs where legally required; keep immutable audit hashes where appropriate.
- Add consent/privacy notice timestamps and purpose limitation metadata for emergency submissions and communications.

---

## 6. Clean Seed & Migration Idempotency

### MEDIUM — Seed file mostly clean but has model drift and idempotency caveats

**Files / lines**
- `database/veterinarydb.sql:20-23` creates DB and uses `DROP TABLE IF EXISTS` in order — good for clean import.
- `database/veterinarydb.sql:27-39,45-67,70-87` seeds exactly 3 accounts: admin/user/vet. Password hashes are bcrypt `$2y$10$...` format.
- `database/veterinarydb.sql:234-269` seeds emergency with `Status='In Treatment'` while code also uses `In Progress`/`Pending` etc.; status vocabulary drift.
- `includes/db.php:43-55` also seeds default accounts when tables empty. On fresh schema import this is harmless, but dual seed sources can drift.
- `receipt_blocks` schema drift noted above means fresh import + runtime ensure produces different shape than schema file alone.

**Root cause**
Seed/bootstrap logic is duplicated across SQL dump and runtime PHP bootstrap.

**Defensive fix**
- Make `database/veterinarydb.sql` the authoritative fresh-install artifact and generate it from migrations.
- Add CI smoke test: import into fresh MariaDB/MySQL, run app bootstrap, assert no errors, no duplicate keys, expected seed rows/hashes, and final schema matches expected checksum.
- Normalize status enums/vocabularies and add CHECK constraints where supported.
- Remove/clearly separate legacy tables and seed data not used by the 3-role model.

---

## Top 5 Highest-Risk Database Bugs To Fix First

1. **CRITICAL — Non-atomic appointment scheduling allows double booking** (`submit_booking.php:95-133`, `includes/scheduling_engine.php:179-427`). Fix with transactional locking, revalidation before insert, and DB uniqueness/guard constraints.
2. **CRITICAL — Emergency triage recalculation has race conditions and no transaction** (`submit_emergency.php:124-160`, `includes/scheduling_engine.php:528-841`, `update_status.php:108-110`, `admin/update_emergency_status.php:92-94`). Fix with a triage lock + transaction around all room/schedule/emergency updates.
3. **CRITICAL — Destructive FK cascades can erase clinical/history records** (`database/veterinarydb.sql:157-158,227-228,268`). Fix by replacing cascades with restrict/soft-delete/anonymization for clinical and historical tables.
4. **HIGH — Receipt blockchain append/idempotency is vulnerable under concurrency and schema drift** (`includes/receipt_blockchain.php:170-260`, `database/veterinarydb.sql:333-360`, `includes/db.php:66-87`). Fix with ledger mutex/unique `(Reference_Type,Reference_ID)`, idempotent duplicate handling, and schema alignment.
5. **HIGH — Role/identity model drift creates authorization and accountability gaps** (`database/veterinarydb.sql:27-39,70-87,161-179`, `signup.php:41-55`, `update_status.php:9-16`, `api/scheduler.php:82-90`). Fix by implementing explicit `owner/vet_staff/head_vet` model, PRC/license constraints, cross-table identity uniqueness including admin, and centralized RBAC.

---

## Audit Coverage Notes

- Reviewed key files: `database/veterinarydb.sql`, `includes/db.php`, `includes/receipt_blockchain.php`, `includes/scheduling_engine.php`, `api/receipt_api.php`, `api/chat_api.php`, `api/scheduler.php`, `submit_booking.php`, `submit_emergency.php`, `update_status.php`, `update_sched_status.php`, `admin/update_emergency_status.php`, `owner_bookings.php`, `get_my_pets.php`, `owner_add_pet.php`, `upload_pet_photo.php`, `cancel_emergency.php`, `receipts.php`, `signup.php`, `register_vet.php`, `rate_limit.php`, `includes/security_crypto.php`.
- Searched PHP/JS for SQL query APIs (`->query`, `->prepare`, `mysqli_query`), DB error leakage, transaction/locking primitives, and role/identity keywords. No Node.js MySQL query files were found under `assets/js`; `ws_server.php` exists but was not identified as a Node MySQL client.
- This is a static defensive audit; runtime DB behavior, actual MariaDB engine/config, and live data volumes were not exercised.
