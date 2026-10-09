const API_BASE = '/api/exp';

async function api(method, path, body, opts = {}) {
  const init = { method, credentials: 'same-origin', headers: {} };
  if (body && !(body instanceof FormData)) {
    init.headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(body);
  } else if (body instanceof FormData) {
    init.body = body;
  }
  const res = await fetch(`${API_BASE}${path}`, init);
  let data;
  try { data = await res.json(); } catch (_) { data = { ok: false, error: { message: 'Invalid JSON response' } }; }
  if (!res.ok || !data.ok) {
    const msg = data?.error?.message || res.statusText;
    const err = new Error(msg);
    err.status = res.status;
    err.code = data?.error?.code;
    err.data = data;
    throw err;
  }
  return data.data;
}

function el(tag, attrs = {}, children = []) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (k === 'class') node.className = v;
    else if (k === 'html') node.innerHTML = v;
    else if (k.startsWith('on') && typeof v === 'function') node.addEventListener(k.slice(2), v);
    else if (v === true) node.setAttribute(k, '');
    else if (v === false || v == null) { /* skip */ }
    else node.setAttribute(k, v);
  }
  for (const c of children) {
    if (c == null) continue;
    node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
  }
  return node;
}

function showNotice(target, message, kind = 'error') {
  const n = el('div', { class: `notice ${kind}` }, [message]);
  target.prepend(n);
  setTimeout(() => n.remove(), 5000);
}

function statusBadge(status) {
  return el('span', { class: `status ${status}` }, [status.replace(/_/g, ' ')]);
}

let currentSession = null;

export { api, el, showNotice, statusBadge, currentSession };
