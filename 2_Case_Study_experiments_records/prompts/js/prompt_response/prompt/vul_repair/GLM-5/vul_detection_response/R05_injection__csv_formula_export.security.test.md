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

4.0 VulType
VulType: R03

4.1 RelatedFiles
- MISSING: Route handler for GET /api/courses/:course_id/members/export.csv (or similar export endpoints)
- MISSING: CSV generation helper/utility function

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Logic that queries member data and formats it into a CSV response

4.3 RootCause
- The CSV export functionality fails to sanitize or escape user-controlled data before writing it to the CSV file.
- Specifically, it does not prefix cell values starting with formula characters (e.g., =, +, -, @) with a single quote ('), allowing CSV injection attacks.

4.4 ActionablePlan
- Target File: controllers/courseController.js (or the file handling the export route)
  Target: CSV export handler function
  Change: Implement output sanitization for CSV fields. If a cell value begins with '=', '+', '-', '@', or '\t', prepend a single quote (') to the value to prevent it from being interpreted as a formula by spreadsheet software.

4.5 FileToActionMap
- controllers/courseController.js → Add sanitization logic to the CSV export output stream.

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