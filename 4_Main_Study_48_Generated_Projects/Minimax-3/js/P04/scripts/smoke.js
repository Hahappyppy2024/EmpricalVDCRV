import http from 'node:http';

const PORT = process.env.PORT || 3000;

function req(path, opts = {}) {
  return new Promise((resolve, reject) => {
    const r = http.request({ host: '127.0.0.1', port: PORT, path, method: opts.method || 'GET', headers: opts.headers || {} }, (res) => {
      let data = '';
      res.on('data', c => data += c);
      res.on('end', () => {
        const ct = res.headers['content-type'] || '';
        let body = data;
        if (ct.includes('application/json') && data) {
          try { body = JSON.parse(data); } catch {}
        }
        resolve({ status: res.statusCode, headers: res.headers, body });
      });
    });
    r.on('error', reject);
    if (opts.body) r.write(typeof opts.body === 'string' ? opts.body : JSON.stringify(opts.body));
    r.end();
  });
}

function jar() {
  let cookies = {};
  function update(setCookie) {
    if (!setCookie) return;
    const arr = Array.isArray(setCookie) ? setCookie : [setCookie];
    for (const sc of arr) {
      const [pair] = sc.split(';');
      const [k, ...rest] = pair.split('=');
      cookies[k.trim()] = rest.join('=').trim();
    }
  }
  function header() {
    const list = Object.entries(cookies).map(([k, v]) => `${k}=${v}`);
    return list.length ? { cookie: list.join('; ') } : {};
  }
  return { update, header };
}

async function main() {
  console.log('[smoke] starting');
  const r0 = await req('/api/health');
  if (r0.status !== 200) throw new Error('health failed ' + r0.status);
  console.log('[smoke] health ok ->', r0.body);

  const cookies = jar();

  let r = await req('/api/hotel/room_search?check_in=2099-01-10&check_out=2099-01-12&guests=2', { headers: cookies.header() });
  console.log('[smoke] guest search status', r.status, 'count', r.body?.count);
  if (r.status !== 200) throw new Error('search failed');

  r = await req('/api/hotel/account_access', { method: 'POST', headers: { 'content-type': 'application/json', ...cookies.header() }, body: { mode: 'login', email: 'alice@example.com', password: 'guest123' } });
  if (r.status !== 200) throw new Error('login failed ' + r.status);
  cookies.update(r.headers['set-cookie']);
  console.log('[smoke] login ok role=' + r.body.user.role);

  r = await req('/api/hotel/room_search?check_in=2099-02-10&check_out=2099-02-12&guests=2', { headers: cookies.header() });
  if (r.status !== 200 || !r.body.results?.length) throw new Error('post-login search failed');
  const room = r.body.results[0];

  r = await req('/api/hotel/booking_creation', { method: 'POST', headers: { 'content-type': 'application/json', ...cookies.header() }, body: { room_id: room.id, check_in: '2099-02-10', check_out: '2099-02-12', guests: 2, card_number: '4242424242424242', card_holder: 'Alice' } });
  if (r.status !== 201) throw new Error('booking create failed ' + r.status + ' ' + JSON.stringify(r.body));
  const bookingId = r.body.booking.id;
  console.log('[smoke] booking created ' + r.body.booking.code);

  r = await req('/api/hotel/guest_messages', { method: 'POST', headers: { 'content-type': 'application/json', ...cookies.header() }, body: { booking_id: bookingId, body: 'Hello, late check-in please.' } });
  if (r.status !== 201) throw new Error('message failed ' + r.status);
  console.log('[smoke] message sent');

  r = await req('/api/hotel/invoice_and_receipt?booking_id=' + bookingId, { headers: cookies.header() });
  if (r.status !== 200) throw new Error('invoice failed ' + r.status);
  console.log('[smoke] invoice ok (HTML ' + r.body.length + ' chars)');

  cookies.update([]);
  r = await req('/api/hotel/account_access', { method: 'POST', headers: { 'content-type': 'application/json' }, body: { mode: 'login', email: 'admin@example.com', password: 'admin123' } });
  if (r.status !== 200) throw new Error('admin login failed');
  cookies.update(r.headers['set-cookie']);
  console.log('[smoke] admin login ok');

  r = await req('/api/hotel/admin_reports?report=revenue', { headers: cookies.header() });
  if (r.status !== 200) throw new Error('report failed');
  console.log('[smoke] admin report ok rows=' + r.body.rows.length);

  r = await req('/api/hotel/frontend_api_integration_and_errors', { method: 'POST', headers: { 'content-type': 'application/json' }, body: { scenario: 'conflict' } });
  if (r.status !== 409) throw new Error('expected 409, got ' + r.status);
  console.log('[smoke] error scenario ok status=' + r.status);

  console.log('[smoke] PASS');
}

main().catch(err => { console.error('[smoke] FAIL', err.message); process.exit(1); });