import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';

const HOST = process.env.HOST || '127.0.0.1';
const PORT = Number(process.env.PORT || 3000);

function request(method, urlPath, body, cookieJar) {
  return new Promise((resolve, reject) => {
    const headers = { 'Content-Type': 'application/json' };
    if (cookieJar.cookie) headers['Cookie'] = cookieJar.cookie;
    const data = body ? Buffer.from(JSON.stringify(body)) : null;
    if (data) headers['Content-Length'] = data.length;
    const req = http.request({ host: HOST, port: PORT, path: urlPath, method, headers }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        const cookies = res.headers['set-cookie'] || [];
        if (cookies.length) cookieJar.cookie = cookies[0].split(';')[0];
        let json = null;
        try { json = text ? JSON.parse(text) : null; } catch (_) { /* ignore */ }
        resolve({ status: res.statusCode, body: json, text });
      });
    });
    req.on('error', reject);
    if (data) req.write(data);
    req.end();
  });
}

function unwrap(res) {
  if (!res.body) throw new Error('empty response');
  if (!res.body.ok) {
    throw new Error(JSON.stringify(res.body.error || res.body));
  }
  return res.body.data;
}

async function step(name, fn) {
  process.stdout.write(`[smoke] ${name} ... `);
  try {
    await fn();
    console.log('OK');
  } catch (err) {
    console.log('FAIL');
    console.error(err.message);
    process.exitCode = 1;
    throw err;
  }
}

async function main() {
  console.log(`Smoke testing http://${HOST}:${PORT}`);
  const jar = { cookie: null };

  await step('health', async () => {
    const r = await request('GET', '/api/health', null, jar);
    if (!r.body || !r.body.ok) throw new Error('health failed');
  });

  await step('login as employee', async () => {
    const r = await request('POST', '/api/exp/account_access/login', { username: 'emp_eng1', password: 'Passw0rd!' }, jar);
    if (r.status !== 200) throw new Error(`login status ${r.status}`);
    const data = unwrap(r);
    if (data.user.role !== 'employee') throw new Error('expected employee role');
  });

  await step('create draft report', async () => {
    const r = await request('POST', '/api/exp/expense_report_creation', { title: 'Smoke Travel', description: 'Quick smoke trip' }, jar);
    if (r.status !== 201) throw new Error(`expected 201 got ${r.status}`);
    const data = unwrap(r);
    jar.reportId = data.id;

    await step('edit report with lines', async () => {
      const upd = await request('PATCH', `/api/exp/expense_report_creation/${data.id}`, {
        lines: [
          { categoryCode: 'TRV', description: 'Taxi', expenseDate: '2025-08-01', amount: 45.5, merchant: 'Yellow Cab' },
          { categoryCode: 'MLS', description: 'Lunch', expenseDate: '2025-08-01', amount: 18.75 }
        ]
      }, jar);
      if (upd.status !== 200) throw new Error(`patch failed ${upd.status}`);
    });

    await step('submit report', async () => {
      const r2 = await request('POST', '/api/exp/report_submission', { reportId: data.id, action: 'submit' }, jar);
      if (r2.status !== 200) throw new Error(`submit ${r2.status} ${JSON.stringify(r2.body)}`);
    });
  });

  await step('login as manager', async () => {
    jar.cookie = null;
    const r = await request('POST', '/api/exp/account_access/login', { username: 'eng_manager', password: 'Passw0rd!' }, jar);
    if (r.status !== 200) throw new Error('manager login failed');
  });

  await step('manager approves', async () => {
    const r = await request('POST', '/api/exp/manager_approval', { reportId: jar.reportId, decision: 'approved', note: 'looks fine' }, jar);
    if (r.status !== 200) throw new Error(`approve ${r.status} ${JSON.stringify(r.body)}`);
  });

  await step('login as finance', async () => {
    jar.cookie = null;
    const r = await request('POST', '/api/exp/account_access/login', { username: 'fin_lead', password: 'Passw0rd!' }, jar);
    if (r.status !== 200) throw new Error('finance login failed');
  });

  await step('finance approves', async () => {
    const r = await request('POST', '/api/exp/finance_review', { reportId: jar.reportId, decision: 'approved', note: 'paid soon' }, jar);
    if (r.status !== 200) throw new Error(`finance ${r.status} ${JSON.stringify(r.body)}`);
  });

  await step('finance generates export', async () => {
    const r = await request('POST', '/api/exp/reimbursement_export', { statuses: ['finance_approved'] }, jar);
    if (r.status !== 201) throw new Error(`export ${r.status} ${JSON.stringify(r.body)}`);
  });

  await step('add comment as employee', async () => {
    jar.cookie = null;
    await request('POST', '/api/exp/account_access/login', { username: 'emp_eng1', password: 'Passw0rd!' }, jar);
    const r = await request('POST', '/api/exp/comments_and_activity', { reportId: jar.reportId, body: 'thanks!' }, jar);
    if (r.status !== 201) throw new Error(`comment ${r.status} ${JSON.stringify(r.body)}`);
  });

  console.log('Smoke test completed successfully.');
}

main().catch(() => { process.exit(1); });
