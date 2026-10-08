import { resetDatabase } from '../src/db/database.js';
import { seedDatabase } from '../src/db/seed.js';
import { createApp } from '../src/app.js';
import { config } from '../src/config.js';

const db = resetDatabase();
seedDatabase(db);

const { httpServer } = createApp();
await new Promise((resolve) => httpServer.listen(0, '127.0.0.1', resolve));
const port = httpServer.address().port;
const base = `http://127.0.0.1:${port}`;

const results = [];
async function check(name, fn) {
  try {
    const detail = await fn();
    results.push({ name, ok: true, detail });
    console.log(`PASS  ${name}`);
  } catch (err) {
    results.push({ name, ok: false, detail: err.message });
    console.log(`FAIL  ${name}: ${err.message}`);
  }
}

let cookie = '';
let smokeReportId = null;
const fetchJson = async (path, opts = {}) => {
  const headers = { ...(opts.headers || {}) };
  if (cookie) headers['Cookie'] = cookie;
  if (opts.body !== undefined && !headers['Content-Type']) headers['Content-Type'] = 'application/json';
  const res = await fetch(base + path, { ...opts, headers });
  const text = await res.text();
  let body = {};
  try { body = JSON.parse(text); } catch (_) { body = { raw: text }; }
  if (!res.ok) {
    const e = new Error(body.error ? body.error.message : `${res.status} ${text.slice(0, 120)}`);
    e.status = res.status;
    e.body = body;
    throw e;
  }
  return { status: res.status, data: body.data, raw: body };
};

const login = async (username, password) => {
  const res = await fetch(base + '/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, password }),
  });
  if (!res.ok) throw new Error(`login failed: ${res.status}`);
  cookie = res.headers.get('set-cookie').split(';')[0];
  return res.json();
};

await check('health', async () => (await fetchJson('/api/health')).data.status);
await check('login employee alice', async () => {
  const r = await login('alice', 'employee123');
  return `user=${r.data.user.username} role=${r.data.user.role}`;
});
await check('GET expense_report_creation (own reports)', async () => (await fetchJson('/api/exp/expense_report_creation')).data.count);
await check('POST expense_report_creation (create draft)', async () => {
  const r = await fetchJson('/api/exp/expense_report_creation', {
    method: 'POST',
    body: JSON.stringify({ title: 'Smoke test report', purpose: 'created by smoke check', lines: [{ category_id: 1, expense_date: '2026-03-01', description: 'Test meal', merchant: 'Cafe', amount: 42.5 }] }),
  });
  smokeReportId = r.data.id;
  return `${r.data.report_no} status=${r.data.status}`;
});
await check('POST report_submission (submit draft)', async () => {
  const r = await fetchJson('/api/exp/report_submission', { method: 'POST', body: JSON.stringify({ report_id: smokeReportId }) });
  return `${r.data.report_no} status=${r.data.status}`;
});
await check('GET manager_approval (mgr1 queue)', async () => {
  await login('mgr1', 'manager123');
  const r = await fetchJson('/api/exp/manager_approval');
  return `queue=${r.data.queue.length}`;
});
await check('POST manager_approval (approve)', async () => {
  const r = await fetchJson('/api/exp/manager_approval', { method: 'POST', body: JSON.stringify({ report_id: smokeReportId, decision: 'approved', comment: 'smoke approved' }) });
  return `${r.data.report.status}`;
});
await check('GET finance_review (queue)', async () => {
  await login('finance1', 'finance123');
  const r = await fetchJson('/api/exp/finance_review');
  return `queue=${r.data.queue.length}`;
});
await check('POST reimbursement_export (CSV)', async () => {
  const r = await fetchJson('/api/exp/reimbursement_export', { method: 'POST', body: JSON.stringify({}) });
  return `${r.data.batch.batch_no} rows=${r.data.batch.row_count}`;
});
await check('POST comments_and_activity', async () => {
  await login('alice', 'employee123');
  const r = await fetchJson('/api/exp/comments_and_activity', { method: 'POST', body: JSON.stringify({ report_id: 1, body: 'smoke comment' }) });
  return `comment=${r.data.comment.id}`;
});
await check('GET policy_rules', async () => (await fetchJson('/api/exp/policy_rules')).data.rules.length);
await check('GET employee_data_access', async () => (await fetchJson('/api/exp/employee_data_access')).data.employees.length);
await check('POST frontend_api_integration_and_errors (conflict simulation)', async () => {
  try {
    await fetchJson('/api/exp/frontend_api_integration_and_errors', { method: 'POST', body: JSON.stringify({ action: 'simulate_conflict', report_id: 4, expected_status: 'draft' }) });
    throw new Error('expected a conflict error but got ok');
  } catch (err) {
    if (err.status === 409) return `conflict expected, got ${err.status}`;
    throw err;
  }
});
await check('admin config GET', async () => {
  await login('admin', 'admin123');
  const r = await fetchJson('/api/exp/admin_configuration');
  return `departments=${r.data.departments.length} users=${r.data.users.length}`;
});
await check('unauthorized access rejected (bob sees alice report)', async () => {
  await login('bob', 'employee123');
  try {
    await fetchJson('/api/exp/expense_report_creation/2');
    throw new Error('expected 403');
  } catch (err) {
    if (err.status === 403) return '403 as expected';
    throw err;
  }
});
await check('invalid login rejected', async () => {
  try {
    const res = await fetch(base + '/api/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ username: 'alice', password: 'wrong' }) });
    if (res.status !== 401) throw new Error(`expected 401 got ${res.status}`);
    return '401 as expected';
  } catch (err) {
    throw err;
  }
});

httpServer.close();
db.close();

const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
if (failed.length) {
  console.log('Failed:', failed.map((f) => f.name).join(', '));
  process.exit(1);
}
