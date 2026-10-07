
You are a web application security auditor (vulnerability detector).

### 1) Task
Diagnose why the SECURITY TEST below fails. Use ONLY the test and the provided code snippets. Do NOT write any code diff in this step. Output a minimal, concrete repair plan for the next step.



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



### 4) Output Requirements (STRICT)

Your response MUST contain exactly the following 6 sections, in this exact order.
Do NOT add extra sections.
Do NOT output any code diff.

4.0 VulType
Choose exactly ONE from: R01 R02 R03 R04 R05 R06 R07 R08 R09 R10
Format:
VulType: Rxx


4.1 RelatedFiles
List ONLY the concrete file names/paths that are involved in the failing request path (/api/courses).
If a required file is not shown in the provided snippets, write: MISSING: <expected file role>

Format:
RelatedFiles:
- <file_path_1>
- <file_path_2>
- MISSING: <role description>


4.2 RelevantCodeInsideFiles
For EACH file listed in 4.1:
- Identify the exact route / middleware / function related to GET /api/courses
- Quote ONLY the minimal relevant code fragment (no full file dump)

Format:
RelevantCodeInsideFiles:
File: <file_path>
- Route/Middleware/Function: <name>
- Relevant Code:
  <minimal code snippet>

If file is MISSING, write:
File: MISSING
- Needed: <what must be located (e.g., route definition for GET /api/courses)>


4.3 RootCause
2–4 bullets explaining:
- What check is missing or incorrect
- Exactly where it should be enforced (route level / middleware level / controller)

Format:
RootCause:
- ...
- ...


4.4 ActionablePlan
Concrete minimal repair steps.
Each bullet MUST include:
- Target File
- Target Function/Route
- What to change (precise behavior, e.g., “add auth middleware before handler” or “return 401 if no session.user”)

Format:
ActionablePlan:
- Target File: ...
  Target: ...
  Change: ...

- Target File: ...
  Target: ...
  Change: ...


4.5 FileToActionMap
Map files → repair actions clearly.

Format:
FileToActionMap:
- <file_path> → <short description of modification>
- <file_path> → <short description of modification>