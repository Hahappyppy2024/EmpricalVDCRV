### 4.0 VulType

VulType: R09

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: Auth/login route/controller for `POST /api/auth/login` (where failed login occurs)
* MISSING: Audit logging module / repository (where audit events are inserted)
* MISSING: Admin audit API route/controller for `GET /api/admin/audit` (where logs are retrieved)
* MISSING: DB schema/init for audit log table (e.g., `audit_log` / `audit_events`) used by the audit API

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: the `POST /api/auth/login` handler showing how authentication failure is handled (status 400/401/403) and whether an audit event is written on failure

File: MISSING

* Needed: the audit insert function (e.g., `logAuditEvent({ action, actor_user_id, ip, meta })`) and how it is called (or not called) from login failure path

File: MISSING

* Needed: the `GET /api/admin/audit` handler showing how audit events are queried and serialized into `{ audit: [...] }`

File: MISSING

* Needed: the audit table definition / DB setup ensuring events persist across requests in the same test DB

---

### 4.3 RootCause

RootCause:

* The failed login path in `POST /api/auth/login` likely returns 400/401/403 **without recording an audit event**, so the audit log lacks `login_failed`/`auth_failed`/`failed_login`.
* Alternatively, an event may be written but with an action name that does not match expected keywords, or it’s written to a different sink/table not exposed by `/api/admin/audit`.
* Enforcement must occur in the **login controller failure branch** (immediately after determining credentials are invalid) and be retrievable via the **admin audit API** using the same DB connection.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Auth controller** (MISSING)
  Target: `POST /api/auth/login` (failure branch when password is wrong)
  Change: On authentication failure, insert an audit event with an `action` string containing one of: `login_failed` / `auth_failed` / `failed_login` (pick one canonical, e.g., `login_failed`). Include minimal metadata (username attempted, ip, timestamp). Do this before sending the 400/401/403 response.

* Target File: **Audit logging repository/module** (MISSING)
  Target: function that inserts audit events
  Change: Ensure it writes to the same persistent DB used by `makeAppWithFreshDb` (not console-only logging). Guarantee fields required by the audit API (`action`, `created_at`, optional `actor_user_id`) exist.

* Target File: **Admin audit API controller/route** (MISSING)
  Target: `GET /api/admin/audit`
  Change: Query audit events from the audit table and return JSON shape `{ audit: [...] }` where each row includes `action`. Ensure it includes failed-login events, ordered newest-first.

* Target File: **DB schema/init** (MISSING)
  Target: audit table creation
  Change: Ensure an audit table exists and is created for fresh DBs (migration/seed). Include columns at least: `audit_id`, `action`, `created_at`, plus optional `actor_user_id`, `ip`, `meta_json`.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: auth login controller (`POST /api/auth/login`) → add audit event write on failed login with action `login_failed` (or `auth_failed`)
* MISSING: audit logger/repo → persist audit events to DB table used by the app/test harness
* MISSING: admin audit route/controller (`GET /api/admin/audit`) → return `{ audit: [...] }` including `action` values from DB
* MISSING: DB schema/init for audit table → ensure audit table exists in fresh DB and supports storing/retrieving failed-login events
