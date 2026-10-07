### 4.0 VulType

VulType: R05

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: API route/controller that serves the members CSV export (one of `/api/courses/:course_id/members/export.csv`, `/members/export`, `/members.csv`)
* MISSING: CSV export utility/formatter (where fields are serialized into CSV cells)
* MISSING: Data access layer for listing course members (query joining `memberships` + `users`)
* MISSING: Authz middleware for course member export (not the focus of this test, but part of the request path)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: the concrete route definition for the export endpoint(s), including which handler generates the CSV response and what member fields are included (at least `username` is expected to appear, since the test injects `users.username = '=2+2'`)

File: MISSING

* Needed: the CSV serialization code that turns member rows into CSV text (likely uses string concatenation / array join / csv library without sanitization)

File: MISSING

* Needed: the DB query that fetches members for a course (must include `users.username` in the exported columns for the injected value to appear)

---

### 4.3 RootCause

RootCause:

* The CSV export output includes user-controlled values (e.g., `users.username`) **without CSV formula escaping**, so a username starting with `=` is emitted as `=2+2` instead of the required escaped form `'=2+2`.
* The missing check should be applied at **CSV generation time** (the boundary where raw fields become CSV cells), not at DB insertion time.
* The sanitization likely does not detect formula-leading characters (`=`, `+`, `-`, `@`) and/or does not prefix with a safe escape character (`'`) before writing the cell.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Members export route/controller** (MISSING)
  Target: handler for `GET /api/courses/:course_id/members/export(.csv)?`
  Change: Before writing CSV, pass every exported cell value through a “CSV-safe” sanitizer; ensure the `username` field is sanitized so a leading `=` becomes `'=...` in output.

* Target File: **CSV serialization utility** (MISSING; could be inline in controller)
  Target: function that converts row objects to CSV lines
  Change: Implement minimal escaping rule: if a cell (after string coercion and trimming leading whitespace, if you do that) starts with one of `=`, `+`, `-`, `@`, prefix it with a single quote `'`. Keep normal CSV quoting (commas/quotes/newlines) intact.

* Target File: **Member listing data access** (MISSING)
  Target: query producing exported columns
  Change: Confirm `users.username` is included in the export rows and that sanitization is applied to it (and any other user-controlled text such as `display_name`, emails, etc.) prior to CSV output.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: members export route/controller → sanitize each exported cell (especially `username`) to escape formula-leading characters by prefixing `'`
* MISSING: CSV formatter/serializer → add a reusable “escape formula injection” step before CSV row assembly
* MISSING: members query/repo → ensure exported fields come from DB as raw values, then sanitized at output boundary (not stored altered)
