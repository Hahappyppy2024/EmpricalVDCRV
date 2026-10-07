You are a web application security engineer (vulnerability repairer).

### 1) Task

Repair the failing SECURITY TEST below with a minimal patch. Use ONLY the test and the provided code snippets. Do NOT introduce new dependencies. Output a concrete, minimal repair plan for the next step (no code diff in this step).


### 2) Function Feature (security property)
Path traversal defense: the upload download API must validate upload_id strictly and reject traversal payloads (e.g., ../../app.js) with a 400 response, never serving arbitrary files.



### 3) Failed Security Test
const { makeAppWithFreshDb } = require('../_helpers');

console.log('SEC TEST VERSION: R08 upload path traversal HARD v1');

function getCourseId(res) {
  const b = res?.body || {};
  return b?.course?.course_id ?? b?.course_id ?? b?.course?.id ?? b?.id ?? null;
}

function short(res) {
  return {
    status: res?.status,
    ct: res?.headers?.['content-type'],
    body: res?.body,
    text: (res?.text || '').slice(0, 200),
  };
}

async function loginOrDie(agent, username, password) {
  const attempts = [
    { path: '/api/login', body: { username, password } },
    { path: '/api/auth/login', body: { username, password } },
    { path: '/api/login', body: { email: username, password } },
    { path: '/api/auth/login', body: { email: username, password } },
  ];
  let last = null;
  for (const a of attempts) {
    const r = await agent.post(a.path).set('Content-Type', 'application/json').send(a.body);
    last = r;
    if ([200, 201, 204, 302].includes(r.status)) return r;
  }
  throw new Error(`Login failed: ${JSON.stringify(short(last), null, 2)}`);
}

async function createCourseOrFail(agent) {
  const attempts = [
    { path: '/api/courses', body: { title: 'C', description: 'D' } },
    { path: '/api/courses', body: { name: 'C', description: 'D' } },
    { path: '/api/courses', body: { name: 'C', desc: 'D' } },
  ];
  let last = null;
  for (const a of attempts) {
    const r = await agent.post(a.path).set('Content-Type', 'application/json').send(a.body);
    last = r;
    if ([200, 201].includes(r.status)) {
      const id = getCourseId(r);
      if (!id) throw new Error(`Course created but cannot extract id: ${JSON.stringify(short(r), null, 2)}`);
      return id;
    }
  }
  throw new Error(`Create course failed: ${JSON.stringify(short(last), null, 2)}`);
}

describe('Security: uploads download path traversal (HARD)', () => {
  test('download endpoint blocks traversal via upload_id (HARD)', async () => {
    const { agent } = makeAppWithFreshDb('sec_up_trav_hard.db');

    await loginOrDie(agent, 'admin', 'admin123');
    const course_id = await createCourseOrFail(agent);

    // 1) Upload must succeed and return upload_id (proves upload endpoint exists)
    const up = await agent
      .post(`/api/courses/${course_id}/uploads`)
      .attach('file', Buffer.from('hello'), { filename: 'hello.txt', contentType: 'text/plain' });

    if (![200, 201].includes(up.status)) {
      throw new Error(`Upload failed: ${JSON.stringify(short(up), null, 2)}`);
    }
    const upload_id = up.body?.upload?.upload_id ?? up.body?.upload_id ?? up.body?.id ?? null;
    if (!upload_id) {
      throw new Error(`Cannot extract upload_id: ${JSON.stringify(short(up), null, 2)}`);
    }

    // 2) Baseline download must succeed (proves route exists & is functional)
    const ok = await agent.get(`/api/courses/${course_id}/uploads/${upload_id}/download`);
    if (![200, 302].includes(ok.status)) {
      throw new Error(`Baseline download failed: ${JSON.stringify(short(ok), null, 2)}`);
    }
    if (ok.status === 200) {
      const ctype = ok.headers?.['content-type'] || '';
      // should look like a file response, not JSON
      expect(ctype).not.toMatch(/application\/json/i);
    }

    // 3) Route existence probe: numeric id that likely doesn't exist
    // If your route is missing, Express default 404 often contains "Cannot GET".
    const probe = await agent.get(`/api/courses/${course_id}/uploads/999999/download`);
    if (probe.status === 404) {
      expect(probe.text || '').not.toMatch(/Cannot GET/i);
    }

    // 4) Attack: traversal MUST be rejected by validation (expect 400)
    // If this returns 404 because route doesn't match traversal segments, the "probe" above
    // should have already caught route-missing case. Here we enforce stricter behavior.
    const bad = await agent.get(`/api/courses/${course_id}/uploads/../../app.js/download`);
    expect(bad.status).toBe(400);

    const badType = bad.headers?.['content-type'] || '';
    expect(badType).not.toMatch(/octet-stream|zip|text\/javascript|application\/javascript/i);
  });
});

### 4.0 VulType

VulType: R08

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: Uploads API router that defines `GET /api/courses/:course_id/uploads/:upload_id/download`
* MISSING: Upload download controller/handler (the code that maps `upload_id` → file path and serves it)
* MISSING: Uploads repository/model that fetches upload metadata (stored filename/path) by `upload_id`
* MISSING: File storage helper (where upload directory is defined and paths are joined/resolved)
* MISSING: Express app configuration for route params / decoding behavior (to confirm how `../../app.js` reaches the handler)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: the exact route definition for download, including whether `:upload_id` is constrained (e.g., `:upload_id(\\d+)`) and what handler it calls

File: MISSING

* Needed: the download handler code that reads `req.params.upload_id` and constructs the filesystem path (look for `path.join`, `sendFile`, `res.download`, `fs.createReadStream`)

File: MISSING

* Needed: the upload metadata lookup by id (SQL query or in-memory mapping). This determines whether traversal strings are ever used as file paths directly.

File: MISSING

* Needed: the storage path helper (base upload directory). This is where path traversal must be prevented (ensure resolved path stays under base dir).

---

### 4.3 RootCause

RootCause:

* `upload_id` is not strictly validated, so a traversal payload like `../../app.js` can reach the download logic instead of being rejected with **400**.
* The download handler likely uses `upload_id` (or a derived value) in filesystem path construction without enforcing an **ID-only contract** (e.g., numeric/UUID) and/or without checking that the resolved path stays within the uploads directory.
* Enforcement must occur at the **route/controller boundary** (validate `upload_id` format early) and defensively at the **file-serving boundary** (resolve + “must stay under base dir” check).

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **Uploads download route file** (MISSING)
  Target: route definition for `GET /api/courses/:course_id/uploads/:upload_id/download`
  Change: Constrain `:upload_id` to an allowed strict pattern (preferably numeric id `^\d+$` or a UUID pattern if that’s what you use). If it doesn’t match, immediately return **400**.

* Target File: **Uploads download controller/handler** (MISSING)
  Target: download handler (e.g., `downloadUpload`)
  Change: Before any DB/file action, validate `upload_id` strictly; on invalid format return **400** with JSON error (and ensure response content-type is not a file type). Do not treat `upload_id` as a path segment.

* Target File: **File storage helper / path join logic** (MISSING)
  Target: function that builds the absolute file path to serve
  Change: Use `path.resolve(baseDir, relativeNameFromDb)` and verify the resolved path starts with `baseDir` (or is within it). If it escapes, return **400/403** and never call `sendFile/download`.

* Target File: **Uploads repository/model** (MISSING)
  Target: `getUploadById(course_id, upload_id)`
  Change: Ensure lookup is by id via parameterized query; never accept filename/path from user input. If no record found, return **404** (but invalid-format must remain **400**).

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: uploads routes file → add strict param validation (pattern constraint) and return 400 on invalid `upload_id`
* MISSING: uploads download controller → validate `upload_id` before DB/path usage; reject traversal payloads with 400
* MISSING: storage/path helper → enforce “resolved path stays under uploads base dir” before serving any file
* MISSING: uploads repo/model → ensure upload lookup is id-based and parameterized; no user-controlled path usage


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