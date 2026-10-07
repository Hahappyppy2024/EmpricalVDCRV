You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).



### 2) Function Feature (security property)
CSV export sanitization: when exporting course members, any cell starting with a formula (e.g., =2+2) must be escaped (e.g., prefixed with ') to prevent CSV/Excel formula injection.


### 3) Failed Security Test
const sqlite3 = require('sqlite3').verbose();
const { makeAppWithFreshDb } = require('../_helpers');

console.log('SEC TEST VERSION: R05 CSV formula injection v5 (DB-seed fully schema-aware)');

function openDb(dbPath) {
  return new sqlite3.Database(dbPath);
}
function run(db, sql, params = []) {
  return new Promise((resolve, reject) => {
    db.run(sql, params, function (err) {
      err ? reject(err) : resolve(this);
    });
  });
}
function all(db, sql, params = []) {
  return new Promise((resolve, reject) => {
    db.all(sql, params, (err, rows) => (err ? reject(err) : resolve(rows)));
  });
}

async function loginOrDie(agent, username, password) {
  const attempts = [
    { path: '/api/login', body: { username, password } },
    { path: '/api/auth/login', body: { username, password } },
  ];
  let last = null;
  for (const a of attempts) {
    const r = await agent.post(a.path).set('Content-Type', 'application/json').send(a.body);
    last = r;
    if ([200, 201, 204, 302].includes(r.status)) return r;
  }
  throw new Error(`R05 cannot login. last status=${last?.status} body=${JSON.stringify(last?.body)}`);
}

function getCourseId(res) {
  const b = res?.body || {};
  return b?.course?.course_id ?? b?.course_id ?? b?.course?.id ?? b?.id ?? null;
}

async function createCourseOrDie(agent) {
  const attempts = [
    { path: '/api/courses', body: { title: 'C', description: 'D' } },
    { path: '/api/courses', body: { name: 'C', description: 'D' } },
  ];
  let last = null;
  for (const a of attempts) {
    const r = await agent.post(a.path).set('Content-Type', 'application/json').send(a.body);
    last = r;
    if ([200, 201].includes(r.status)) {
      const id = getCourseId(r);
      if (!id) throw new Error(`Course created but cannot extract id`);
      return id;
    }
  }
  throw new Error(`Create course failed. last status=${last?.status} body=${JSON.stringify(last?.body)}`);
}

async function insertEvilUser(db) {
  const tries = [
    {
      sql: "INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)",
      params: ['=2+2', 'x', 'Evil'],
    },
    {
      sql: "INSERT INTO users (username, password_hash, display_name, created_at) VALUES (?, ?, ?, datetime('now'))",
      params: ['=2+2', 'x', 'Evil'],
    },
    {
      sql: "INSERT INTO users (username, password_hash, display_name, created_at, updated_at) VALUES (?, ?, ?, datetime('now'), datetime('now'))",
      params: ['=2+2', 'x', 'Evil'],
    },
  ];

  let lastErr = null;
  for (const t of tries) {
    try {
      await run(db, t.sql, t.params);
      return;
    } catch (e) {
      lastErr = e;
    }
  }
  throw lastErr;
}

async function insertMembership(db, course_id, user_id) {
  const tries = [
    {
      sql: "INSERT INTO memberships (course_id, user_id, role_in_course) VALUES (?, ?, ?)",
      params: [course_id, user_id, 'student'],
    },
    {
      sql: "INSERT INTO memberships (course_id, user_id, role_in_course, created_at) VALUES (?, ?, ?, datetime('now'))",
      params: [course_id, user_id, 'student'],
    },
    {
      sql: "INSERT INTO memberships (course_id, user_id, role_in_course, created_at, updated_at) VALUES (?, ?, ?, datetime('now'), datetime('now'))",
      params: [course_id, user_id, 'student'],
    },
  ];

  let lastErr = null;
  for (const t of tries) {
    try {
      await run(db, t.sql, t.params);
      return;
    } catch (e) {
      lastErr = e;
    }
  }
  throw lastErr;
}

describe('R05 Injection: CSV formula injection', () => {
  test('SEC-CSV1: Export members CSV mitigates formula injection', async () => {
    const helperOut = makeAppWithFreshDb('r05_sec_csv1.db');
    const { agent } = helperOut;
    const dbPath = process.env.DB_PATH || helperOut.dbPath || helperOut.db_path;

    if (!dbPath) {
      console.warn('WARN(R05): DB_PATH not available; cannot seed DB. Passing as not-testable.');
      return;
    }

    await loginOrDie(agent, 'admin', 'admin123');
    const course_id = await createCourseOrDie(agent);

    const db = openDb(dbPath);
    try {
      await insertEvilUser(db);

      const rows = await all(db, "SELECT user_id FROM users WHERE username = ?", ['=2+2']);
      if (!rows.length) throw new Error('Inserted user not found after insert.');
      const evil_user_id = rows[0].user_id;

      await insertMembership(db, course_id, evil_user_id);
    } catch (e) {
      throw new Error(`R05 DB seed failed (schema mismatch).\nDB_PATH=${dbPath}\nError: ${e.message}`);
    } finally {
      db.close();
    }

    const exportCandidates = [
      `/api/courses/${course_id}/members/export.csv`,
      `/api/courses/${course_id}/members/export`,
      `/api/courses/${course_id}/members.csv`,
    ];

    let exp = null;
    for (const p of exportCandidates) {
      const r = await agent.get(p);
      if (r.status === 200) {
        exp = r;
        break;
      }
    }
    if (!exp) {
      console.warn('WARN(R05): export endpoint not found (404 on all candidates). Passing as not-testable.');
      return;
    }

    const out = exp.text || '';
    expect(out).toMatch(/'=2\+2/);
  });
});



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


Output Requirements (STRICT):
Your response MUST contain exactly these 3 sections, in this exact order.

[1] Patch
Output EXACTLY ONE unified diff (git diff style). No alternative patches.

[2] Evidence Mapping
Provide 2–4 bullet lines in the form:
- Signal: <failing test / finding> -> Location: <file:function/route> -> Fix: <<=12 words>

[3] FailureModeGuess
failure_mode_guess: "<Regression|ContractDrift|WrongLocalization|NotFixed|BudgetExceeded|NewSecIssue|N/A>"
failure_mode_guess_reason: "<one sentence tied to signals/patch scope>"