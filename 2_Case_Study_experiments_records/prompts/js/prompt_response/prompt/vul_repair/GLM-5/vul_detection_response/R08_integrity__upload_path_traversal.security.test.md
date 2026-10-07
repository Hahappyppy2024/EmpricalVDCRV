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

4.0 VulType
VulType: R01

4.1 RelatedFiles
- MISSING: Route handler for GET /api/courses/:course_id/uploads/:upload_id/download
- MISSING: Controller logic for serving uploaded files

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Logic that accepts upload_id and sends a file in response

4.3 RootCause
- The application constructs the file path for downloads using the user-supplied upload_id without sanitization or validation.
- It fails to check for path traversal sequences (e.g., ../) or ensure the resolved path remains within the designated upload directory, allowing attackers to access arbitrary files on the server.

4.4 ActionablePlan
- Target File: routes/uploads.js (or controller handling the download)
  Target: Download endpoint handler
  Change: Add strict input validation for upload_id (e.g., allow only alphanumeric/UUID characters) or use a secure file serving library that canonicalizes paths and verifies the target is within the allowed directory. Return 400 Bad Request if validation fails.

4.5 FileToActionMap
- routes/uploads.js → Add path traversal validation to the download handler.

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