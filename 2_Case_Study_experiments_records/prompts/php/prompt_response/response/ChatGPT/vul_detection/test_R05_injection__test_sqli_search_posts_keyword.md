### 4.0 VulType

VulType: R05

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: API route definition for `GET /api/courses/<course_id>/search/posts` (search endpoint)
* MISSING: Search controller/handler that builds the SQL query using `keyword`
* MISSING: Post repository/DAO function that queries posts by keyword (likely uses SQL `LIKE`)
* MISSING: DB access layer (sqlite cursor/connection wrapper) used by the search query (where parameter binding vs string concat happens)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: route wiring for `GET /api/courses/<course_id>/search/posts` and the handler name it calls.

File: MISSING

* Needed: handler logic that reads `keyword` from query params and passes it into DB query (must show whether it uses parameter placeholders or string interpolation).

File: MISSING

* Needed: repository/DAO SQL for searching posts (must show SQL shape, e.g. `... WHERE title LIKE '%{keyword}%' OR body LIKE ...` vs `... WHERE title LIKE ?`).

File: MISSING

* Needed: DB execution helper showing whether it uses `execute(sql)` vs `execute(sql, params)` and how exceptions are handled.

---

### 4.3 RootCause

RootCause:

* The search implementation likely **concatenates/interpolates** the `keyword` into the SQL string (e.g., `... LIKE '%" + keyword + "%'`), so the payload `' OR 1=1 --` turns the WHERE clause into a tautology and returns unrelated posts (e.g., `t2`), failing the “no broadening” oracle.
* Even if `LIKE` is used, missing parameter binding (or incorrect escaping) allows the injected `'` to break out of the string literal and alter SQL semantics.
* Enforcement must occur in the **search repository/DAO** (use parameterized queries) and the **controller** should treat `keyword` as data (optionally validate length/type) and never build SQL by string concatenation.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Search route/controller** (MISSING)
  Target: `GET /api/courses/<course_id>/search/posts`
  Change: Read `keyword` as a plain string; pass it to the repository as a parameter value only. Do **not** embed it in SQL. Optionally: if keyword contains NUL/too long/empty, return 400 or treat as “no results” (but keep baseline search working).

* Target File: **Posts search repository/DAO** (MISSING)
  Target: SQL query that filters posts by `keyword`
  Change: Convert to parameterized SQL:

  * Use placeholders (`?`) and pass `%{keyword}%` as bound params for `LIKE`.
  * Ensure the query is scoped to the course (`course_id = ?`) so it cannot leak across courses either.
  * Example behavior requirement (not code): `WHERE course_id=? AND (title LIKE ? OR body LIKE ?)` with params `[course_id, like, like]`.

* Target File: **DB execution layer** (MISSING)
  Target: query execution helper used by search
  Change: Ensure all executions use bound parameters (`execute(sql, params)`), and on SQL errors return a controlled `400`/empty result without leaking DB internals (so future leak checks don’t fail).

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: search route/controller → stop building SQL strings from `keyword`; pass keyword to DAO as data
* MISSING: posts search DAO/repository → parameterize `LIKE` query; include `course_id` scoping; no string interpolation
* MISSING: DB helper/executor → enforce parameter binding usage and controlled error handling for malformed queries
