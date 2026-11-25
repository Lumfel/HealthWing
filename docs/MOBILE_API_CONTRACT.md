# HealthWing Mobile API Contract

Source of truth for the Flutter mobile client integration with the HealthWing PHP backend.

## Scope and Rules

- Base path: `/api/mobile/`
- Transport: HTTPS in production; JSON request and response bodies.
- Authentication: stateless HMAC-SHA256 Bearer token issued by `POST /api/mobile/auth.php?action=login`.
- Token signing: tokens are signed with `hw_get_master_key()` and verified with `hash_equals`.
- Token claims: `user_id`, `username`, `role`, `prc_license_no`, `iat`, `exp`.
- Allowed roles: `owner`, `vet_staff`, `head_vet`.
- Role model: strict 3-role RBAC only. No standalone `admin` role.
- Compliance: RA 10173 informed consent is required for registration; RA 9268 PRC license metadata is carried for veterinarian identities and receipt attending clinician metadata.
- Server-side validation and DB locks remain authoritative. Mobile clients must handle validation/conflict errors.

## Common Headers

| Header | Required | Value / Notes |
|---|---:|---|
| `Content-Type` | For POST JSON bodies | `application/json` |
| `Accept` | Recommended | `application/json` |
| `Authorization` | Required for protected endpoints | `Bearer <token>` |

## Common Success Envelope

Most endpoints return:

```json
{
  "success": true,
  ...
}
```

## Common Error Envelope

Errors return:

```json
{
  "success": false,
  "error": "error_code",
  "message": "Human-readable message"
}
```

Some validation/runtime failures may include additional fields documented per endpoint.

## Common Error Codes

| HTTP | error | Meaning |
|---:|---|---|
| 400 | `invalid_json` | Request body is not valid JSON. |
| 400 | `invalid_action` | Missing/unsupported `action` query parameter. |
| 400 | `missing_credentials` | Login missing username/password. |
| 400 | `missing_appointment_time` | Booking missing appointment_time. |
| 400 | `missing_pet` | Booking missing pet_id or pet_name. |
| 400 | `missing_code` | Receipt code query parameter missing. |
| 400 | `missing_action` | Vet clinical action missing. |
| 400 | `invalid_input` | Required fields missing or invalid. |
| 400 | `invalid_role` | Registration role not allowed. |
| 400 | `privacy_consent_required` | RA 10173 consent not explicitly granted. |
| 400 | `weak_password` | Password shorter than 8 characters. |
| 400 | `prc_license_required` | vet_staff registration missing PRC license. |
| 401 | `authentication_required` | Missing Bearer token. |
| 401 | `invalid_or_expired_token` | Token signature invalid or expired. |
| 401 | `invalid_credentials` | Invalid login credentials. |
| 403 | `forbidden` | Authenticated but role/ownership/boundary check failed. |
| 403 | `account_pending` | Veterinarian account pending approval. |
| 403 | `account_rejected` | Veterinarian account rejected. |
| 403 | `account_not_approved` | Veterinarian account is not approved for clinical actions. |
| 404 | `owner_not_found` | Owner record not found or deleted. |
| 404 | `veterinarian_not_found` | Veterinarian record not found or deleted. |
| 404 | `pet_not_found` | Pet record not found or deleted. |
| 404 | `receipt_not_found` | Receipt code not found. |
| 405 | `method_not_allowed` | HTTP method not allowed for endpoint/action. |
| 409 | `account_exists` | Username/email already exists across owner/veterinarian. |
| 409 | `prc_license_exists` | PRC license already registered. |
| 409 | `active_emergency_exists` | Owner/pet already has an active emergency request. |
| 409 | `booking_failed` | Transactional booking conflict or slot unavailable. |
| 429 | *(rate limit)* | Rate limit exceeded; response includes `retry_after`. |
| 500 | `database_unavailable` | DB connection failure. |
| 500 | `registration_failed` | Registration could not be completed. |
| 500 | `receipt_decrypt_failed` | Receipt payload could not be decrypted. |
| 500 | `save_failed` | Clinical note save failed. |
| 500 | `update_failed` | Room update failed. |
| 500 | `emergency_submission_failed` | Emergency submission failed. |
| 503 | `registration_busy` | Identity lock unavailable. |
| 503 | `scheduler_busy` | Schedule lock unavailable. |
| 503 | `triage_busy` | Triage lock unavailable. |

Rate-limit response shape (from `rate_limit.php`):

```json
{
  "success": false,
  "message": "Too many requests. Please wait a bit and try again.",
  "retry_after": 30
}
```

## 1) Authentication

### POST `/api/mobile/auth.php?action=login`

Authenticates an owner or veterinarian and returns a stateless mobile token and WebSocket token.

- Required header: `Content-Type: application/json`
- Auth: none
- Rate limit: `mobile_login`, 15 requests / 60 seconds / IP

Request JSON:

| Field | Type | Required | Notes |
|---|---|---:|---|
| `username` | string | Yes | Username or email. |
| `password` | string | Yes | Plain password; verified with `password_verify`. |

Success response `200`:

```json
{
  "success": true,
  "token": "<mobile_hmac_sha256_bearer_token>",
  "ws_token": "<ws_hmac_sha256_token>",
  "user": {
    "id": 1,
    "username": "owner1",
    "role": "owner",
    "first_name": "Jane",
    "last_name": "Doe",
    "email": "jane@example.com",
    "contact": "09170000000",
    "prc_license_no": null
  }
}
```

Veterinarian login `user` object includes:

| Field | Type | Notes |
|---|---|---|
| `id` | integer | Veterinarian_ID. |
| `username` | string | Veterinarian username. |
| `role` | string | `vet_staff` or `head_vet`. |
| `first_name` | string | First name. |
| `last_name` | string | Last name. |
| `email` | string | Email. |
| `contact` | string | Contact number. |
| `prc_license_no` | string | PRC license number. |
| `specialties` | string | Specialties string. |

Error codes: `missing_credentials`, `invalid_credentials`, `account_pending`, `account_rejected`, `database_unavailable`, `method_not_allowed`, `invalid_action`.

### POST `/api/mobile/auth.php?action=register`

Registers an owner or vet_staff account. Veterinarian self-registration creates a `Pending` vet_staff account.

- Required header: `Content-Type: application/json`
- Auth: none
- Rate limit: `mobile_register`, 10 requests / 300 seconds / IP

Request JSON:

| Field | Type | Required | Notes |
|---|---|---:|---|
| `privacy_consent` | boolean | Yes | Must be `true` (RA 10173). |
| `first_name` | string | Yes | Non-empty. |
| `last_name` | string | Yes | Non-empty. |
| `email` | string | Yes | Valid email; normalized to lowercase. |
| `username` | string | Yes | Non-empty. |
| `password` | string | Yes | Minimum 8 characters. |
| `role` | string | Yes | `owner` or `vet_staff`. |
| `age` | integer/null | No | Optional. |
| `address` | string | No | Optional. |
| `contact` | string | No | Optional. |
| `specialties` | string | No | Veterinarian specialties. |
| `prc_license_no` | string | vet_staff only | Required and unique for vet_staff. |

Success response `201`:

```json
{
  "success": true,
  "message": "Owner account created successfully.",
  "user": {
    "id": 10,
    "username": "owner1",
    "role": "owner",
    "first_name": "Jane",
    "last_name": "Doe",
    "email": "jane@example.com",
    "contact": "09170000000",
    "prc_license_no": null,
    "status": "Active"
  }
}
```

For `vet_staff`, `message` indicates pending approval, `status` is `Pending`, and `prc_license_no` is returned.

Error codes: `privacy_consent_required`, `invalid_input`, `weak_password`, `invalid_role`, `prc_license_required`, `registration_busy`, `account_exists`, `prc_license_exists`, `registration_failed`, `database_unavailable`.

## 2) Owner Dashboard

### GET `/api/mobile/owner_dashboard.php`

Returns owner dashboard data.

- Required header: `Authorization: Bearer <token>`
- Auth: required
- Role: `owner`

Request JSON: none.

Success response `200`:

```json
{
  "success": true,
  "owner": {
    "id": 1,
    "username": "owner1",
    "first_name": "Jane",
    "last_name": "Doe",
    "email": "jane@example.com",
    "contact": "09170000000"
  },
  "active_pets": [
    {
      "pet_id": 1,
      "pet_name": "Mochi",
      "pet_type": "Dog",
      "species": "Golden Retriever",
      "age": 3,
      "weight_kg": 24.5,
      "photo_path": null,
      "created_at": "2026-01-01 10:00:00"
    }
  ],
  "upcoming_schedules": [
    {
      "schedule_id": 10,
      "pet_id": 1,
      "pet_name": "Mochi",
      "services": "Wellness Care",
      "appointment_time": "2026-12-01 10:00:00",
      "end_time": "2026-12-01 10:30:00",
      "duration_minutes": 30,
      "status": "Pending",
      "room_id": 1,
      "room_name": "Main Consultation Room",
      "veterinarian_id": 1,
      "veterinarian_name": "Dr. Camille Reyes",
      "veterinarian_prc_license_no": "PRC-VET-004821",
      "notes": null,
      "preempted_by_emergency_id": null,
      "previous_appointment_time": null
    }
  ],
  "active_emergency_triage_status": [
    {
      "booking_id": 5,
      "pet_id": 1,
      "pet_name": "Mochi",
      "status": "In Progress",
      "severity_level": "Emergent",
      "priority_score": 78.5,
      "queue_position": 1,
      "assigned_vet_id": 1,
      "assigned_vet_name": "Dr. Camille Reyes",
      "assigned_vet_prc_license_no": "PRC-VET-004821",
      "assigned_room_id": 3,
      "assigned_room_name": "Emergency Trauma Bay 1",
      "triage_rationale": "Queue #1 | Score 78.5/100.",
      "call_enabled": true,
      "created_at": "2026-12-01 09:00:00"
    }
  ],
  "active_receipt_blocks": [
    {
      "block_id": 1,
      "block_index": 1,
      "receipt_code": "RCPT-...",
      "reference_type": "schedule",
      "reference_id": 10,
      "service_summary": "Wellness Care",
      "amount": 500.0,
      "currency": "PHP",
      "status": "ACTIVE",
      "block_hash": "<sha256>",
      "prev_hash": "<sha256>",
      "merkle_root": "<sha256>",
      "issued_by": "Dr. Camille Reyes",
      "attending_vet_id": 1,
      "attending_vet_name": "Dr. Camille Reyes",
      "attending_clinician_prc": "PRC-VET-004821",
      "created_at": "2026-12-01 10:30:00",
      "expires_at": "2027-12-01 10:30:00"
    }
  ],
  "meta": {
    "purged_expired_receipts": 0,
    "server_time": "2026-12-01 11:00:00"
  }
}
```

Error codes: `authentication_required`, `invalid_or_expired_token`, `forbidden`, `owner_not_found`, `database_unavailable`, `method_not_allowed`.

## 3) Booking and Emergency

### POST `/api/mobile/booking.php?action=book`

Creates a schedule booking using the transactional scheduler flow and `lockAndVerifySlotAvailable()`.

- Required header: `Authorization: Bearer <token>`, `Content-Type: application/json`
- Auth: required
- Role: `owner`
- Rate limit: `mobile_book`, 20 requests / 300 seconds / IP

Request JSON:

| Field | Type | Required | Notes |
|---|---|---:|---|
| `service` | string | No | Defaults to `Wellness Care`. |
| `appointment_time` | string | Yes | Datetime string parseable by scheduler/PHP strtotime. |
| `pet_id` | integer | Cond. | Required if `pet_name` not provided; must belong to owner. |
| `pet_name` | string | Cond. | Required if `pet_id` not provided; existing owner pet reused or new pet created. |
| `pet_species` | string | No | Used when creating a new pet; default `Dog`. |
| `preferred_vet_id` | integer | No | Preferred veterinarian. |
| `details` | string | No | Notes/details; `notes` also accepted. |

Success response `201`:

```json
{
  "success": true,
  "message": "Appointment booked successfully.",
  "booking": {
    "schedule_id": 123,
    "owner_id": 1,
    "pet_id": 1,
    "pet_name": "Mochi",
    "service": "Wellness Care",
    "appointment_time": "2026-12-01 10:00:00",
    "end_time": "2026-12-01 10:30:00",
    "duration_minutes": 30,
    "status": "Pending",
    "was_shifted": false,
    "requested_time": "2026-12-01 10:00:00",
    "veterinarian_id": 1,
    "veterinarian_name": "Camille Reyes",
    "veterinarian_specialty": "Wellness",
    "room_id": 1,
    "room_name": "Main Consultation Room",
    "notes": ""
  }
}
```

Error codes: `missing_appointment_time`, `missing_pet`, `owner_not_found`, `scheduler_busy`, `booking_failed`, `authentication_required`, `invalid_or_expired_token`, `forbidden`, `database_unavailable`.

### POST `/api/mobile/booking.php?action=emergency`

Creates an emergency booking and recalculates triage.

- Required header: `Authorization: Bearer <token>`, `Content-Type: application/json`
- Auth: required
- Role: `owner`
- Rate limit: `mobile_emergency`, 10 requests / 300 seconds / IP

Request JSON:

| Field | Type | Required | Notes |
|---|---|---:|---|
| `details` | string | No | Emergency details; `additional_details` also accepted. |
| `pet_id` | integer | No | Optional pet association. |
| `severity_level` | string | No | One of `Critical`, `Emergent`, `Urgent`, `Semi-Urgent`; default `Urgent`. |
| `allow_concurrent` | boolean | No | If false/default, active duplicate emergency is rejected. |

Success response `201`:

```json
{
  "success": true,
  "message": "Emergency request #55 triaged at Queue Position #1.",
  "emergency": {
    "booking_id": 55,
    "owner_id": 1,
    "pet_id": 1,
    "status": "Pending",
    "severity_level": "Urgent",
    "triage": {
      "booking_id": 55,
      "queue_position": 1,
      "priority_score": 78.5,
      "severity_level": "Urgent",
      "assigned_room_name": "Emergency Trauma Bay 1",
      "assigned_vet_name": "Dr. Camille Reyes",
      "rationale": "..."
    },
    "preempted_schedules": []
  }
}
```

`triage` may be `null` if the case was not present in the returned queue; clients should handle null safely.

Error codes: `owner_not_found`, `triage_busy`, `active_emergency_exists`, `emergency_submission_failed`, `authentication_required`, `invalid_or_expired_token`, `forbidden`, `database_unavailable`.

## 4) Receipt Verification

### GET `/api/mobile/receipt.php?code=...`

Returns a decrypted receipt, chain hash metadata, and attending vet PRC metadata.

- Required header: `Authorization: Bearer <token>`
- Auth: required
- Roles: `owner`, `vet_staff`, `head_vet`
- Owner ownership rule: owners can only access receipts where `receipt_blocks.Owner_ID` matches their token `user_id` and the receipt is ACTIVE and not expired.

Query parameters:

| Parameter | Type | Required | Notes |
|---|---|---:|---|
| `code` | string | Yes | Receipt code (`Receipt_Code`). |

Success response `200`:

```json
{
  "success": true,
  "receipt": {
    "block_id": 1,
    "block_index": 1,
    "receipt_code": "RCPT-...",
    "owner_id": 1,
    "reference_type": "schedule",
    "reference_id": 10,
    "service_summary": "Wellness Care",
    "amount": 500.0,
    "currency": "PHP",
    "status": "ACTIVE",
    "issued_by": "Dr. Camille Reyes",
    "created_at": "2026-12-01 10:30:00",
    "expires_at": "2027-12-01 10:30:00",
    "chain": {
      "block_hash": "<sha256>",
      "prev_hash": "<sha256>",
      "merkle_root": "<sha256>",
      "verified": true
    },
    "attending_vet": {
      "veterinarian_id": 1,
      "name": "Dr. Camille Reyes",
      "prc_license_no": "PRC-VET-004821"
    },
    "payload": {
      "receipt_code": "RCPT-...",
      "owner_name": "Jane Doe",
      "pet_name": "Mochi",
      "service_summary": "Wellness Care",
      "amount": 500.0,
      "notes": "",
      "issued_by": "Dr. Camille Reyes"
    }
  }
}
```

Behavior notes:
- Decrypts payload via `includes/receipt_blockchain.php` helpers/constants and `hw_decrypt_gcm` using AAD `receipt:<code>`.
- Logs access with `hw_log_audit()` action `mobile_receipt_view`.

Error codes: `missing_code`, `receipt_not_found`, `forbidden`, `receipt_decrypt_failed`, `authentication_required`, `invalid_or_expired_token`, `database_unavailable`, `method_not_allowed`.

## 5) Veterinarian Clinical Workstation

### GET `/api/mobile/vet_clinical.php?action=overview`

Returns clinical overview data for veterinarians.

- Required header: `Authorization: Bearer <token>`
- Auth: required
- Roles: `vet_staff`, `head_vet`

Request JSON: none.

Success response `200`:

```json
{
  "success": true,
  "action": "overview",
  "cipher": "AES-256-GCM",
  "current_vet": {
    "veterinarian_id": 1,
    "name": "Dr. Camille Reyes",
    "role": "vet_staff",
    "prc_license_no": "PRC-VET-004821"
  },
  "pets": [
    {
      "Pet_ID": 1,
      "Pet_Name": "Mochi",
      "OwnerFirstName": "Jane",
      "OwnerLastName": "Doe",
      "Description": "Decrypted text",
      "Past_Conditions": "Decrypted text",
      "Current_Ailment": "Decrypted text",
      "Current_Medications": "Decrypted text",
      "CheckupCount": 2,
      "LastCheckup": "2026-12-01"
    }
  ],
  "rooms": [
    {
      "Room_ID": 1,
      "Room_Name": "Main Consultation Room",
      "Room_Type": "General",
      "Status": "idle"
    }
  ],
  "recent_checkups": [
    {
      "Checkup_ID": 10,
      "Pet_ID": 1,
      "Pet_Name": "Mochi",
      "Veterinarian_ID": 1,
      "Diagnosis": "Decrypted text",
      "Prescription": "Decrypted text",
      "Notes": "Decrypted text",
      "Checkup_Date": "2026-12-01"
    }
  ]
}
```

Notes:
- Clinical pet/checkup fields are decrypted for authorized vet roles.
- Overview response keeps DB row field naming for compatibility with existing clinical payload conventions.

Error codes: `missing_action`, `invalid_action`, `authentication_required`, `invalid_or_expired_token`, `forbidden`, `veterinarian_not_found`, `account_not_approved`, `database_unavailable`, `method_not_allowed`.

### POST `/api/mobile/vet_clinical.php?action=save_clinical_note`

Saves an encrypted clinical note/checkup entry.

- Required header: `Authorization: Bearer <token>`, `Content-Type: application/json`
- Auth: required
- Roles: `vet_staff`, `head_vet`

Request JSON:

| Field | Type | Required | Notes |
|---|---|---:|---|
| `pet_id` | integer | Yes | Target pet. |
| `checkup_date` | string (YYYY-MM-DD) | No | Defaults to current date. |
| `diagnosis` | string | No | Encrypted before storage. |
| `prescription` | string | No | Encrypted before storage. |
| `notes` | string | No | Encrypted before storage as combined notes. |
| `update_pet_record` | boolean | No | If true, updates pet Current_Ailment/Current_Medications. |

Success response `201`:

```json
{
  "success": true,
  "action": "save_clinical_note",
  "message": "Clinical note saved.",
  "checkup_id": 101,
  "pet_id": 1,
  "updated_pet_record": true,
  "cipher": "AES-256-GCM"
}
```

Audit logging: writes `hw_log_audit()` with action `save_clinical_note`, target `pet:<pet_id>`, including clinician identity, PRC license, checkup_id, and update flag.

Error codes: `invalid_input`, `pet_not_found`, `save_failed`, `authentication_required`, `invalid_or_expired_token`, `forbidden`, `veterinarian_not_found`, `account_not_approved`, `database_unavailable`.

### POST `/api/mobile/vet_clinical.php?action=update_room`

Updates a room status with role boundary enforcement.

- Required header: `Authorization: Bearer <token>`, `Content-Type: application/json`
- Auth: required
- Roles: `vet_staff`, `head_vet`

Request JSON:

| Field | Type | Required | Notes |
|---|---|---:|---|
| `room_id` | integer | Yes | Room to update. |
| `status` | string | Yes | `idle`, `ongoing`, or `booked`. |

Room boundary rule:
- `head_vet`: may update any room.
- `vet_staff`: may update only rooms assigned to them by `room.Veterinarian_ID`, current emergency `Assigned_Vet_ID`, or current schedule `Veterinarian_ID`.

Success response `200`:

```json
{
  "success": true,
  "action": "update_room",
  "message": "Room status updated.",
  "room_id": 3,
  "status": "ongoing",
  "updated": true
}
```

Audit logging: writes `hw_log_audit()` with action `update_room`, target `room:<room_id>`, including clinician identity, PRC license, room_id, and status.

Error codes: `invalid_input`, `forbidden`, `update_failed`, `authentication_required`, `invalid_or_expired_token`, `veterinarian_not_found`, `account_not_approved`, `database_unavailable`.

## Mobile Token Notes

- Header format: `Authorization: Bearer <token>`
- Token format: `<base64url(payload_json)>.<base64url(hmac_sha256_signature)>`
- Signature: HMAC-SHA256 over the base64url payload using `hw_get_master_key()`.
- Verification: uses `hash_equals` for signature comparison and rejects expired tokens (`exp <= now`).
- Payload fields: `user_id` (int), `username` (string), `role` (`owner|vet_staff|head_vet`), `prc_license_no` (string|null), `iat` (int unix seconds), `exp` (int unix seconds).

## Implementation Files

- `api/mobile/_bootstrap.php`
- `api/mobile/auth.php`
- `api/mobile/owner_dashboard.php`
- `api/mobile/booking.php`
- `api/mobile/receipt.php`
- `api/mobile/vet_clinical.php`
