export async function api(path, options = {}) {
  const response = await fetch(path, {
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  });
  const contentType = response.headers.get('content-type') || '';
  const body = contentType.includes('application/json') ? await response.json() : await response.text();
  if (!response.ok) {
    const message = body && body.error ? body.error.message : `Request failed with status ${response.status}`;
    const err = new Error(message);
    err.status = response.status;
    err.code = body && body.error ? body.error.code : 'HTTP_ERROR';
    err.details = body && body.error ? body.error.details : null;
    throw err;
  }
  return body;
}

export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

export function money(n) {
  return `$${Number(n || 0).toLocaleString()}`;
}

export function badge(status) {
  return `<span class="badge ${escapeHtml(status)}">${escapeHtml(status)}</span>`;
}

export function showToast(message, type = 'info') {
  let area = document.getElementById('toast-area');
  if (!area) {
    area = document.createElement('div');
    area.id = 'toast-area';
    area.style.position = 'fixed';
    area.style.bottom = '16px';
    area.style.right = '16px';
    area.style.zIndex = '100';
    area.style.display = 'flex';
    area.style.flexDirection = 'column';
    area.style.gap = '8px';
    document.body.appendChild(area);
  }
  const div = document.createElement('div');
  div.className = `alert alert-${type}`;
  div.style.margin = '0';
  div.style.boxShadow = '0 4px 12px rgba(0,0,0,0.12)';
  div.textContent = message;
  area.appendChild(div);
  setTimeout(() => {
    div.style.opacity = '0';
    div.style.transition = 'opacity 0.4s';
    setTimeout(() => div.remove(), 400);
  }, 4000);
}

export function renderError(container, err) {
  container.innerHTML = `
    <div class="alert alert-error">
      <strong>${escapeHtml(err.code || 'ERROR')}</strong> — ${escapeHtml(err.message)}
      ${err.details && err.details.missing ? `<div class="mt-2">Missing fields: ${err.details.missing.join(', ')}</div>` : ''}
    </div>`;
}

export function wsConnect(onMessage) {
  const proto = window.location.protocol === 'https:' ? 'wss' : 'ws';
  const ws = new WebSocket(`${proto}://${window.location.host}/ws`);
  ws.onmessage = (event) => {
    try {
      const data = JSON.parse(event.data);
      if (typeof onMessage === 'function') onMessage(data);
    } catch {
      // ignore malformed frames
    }
  };
  return ws;
}

export function today() {
  const d = new Date();
  return d.toISOString().slice(0, 10);
}

export function addDays(base, days) {
  const d = new Date(base + 'T00:00:00Z');
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}

export function setWsStatus(ws) {
  const dot = document.getElementById('ws-status');
  const label = document.getElementById('ws-label');
  if (!dot) return;
  const on = () => { dot.classList.add('on'); if (label) label.textContent = 'realtime connected'; };
  const off = () => { dot.classList.remove('on'); if (label) label.textContent = 'realtime disconnected'; };
  ws.onopen = on;
  ws.onclose = off;
  if (ws.readyState === WebSocket.OPEN) on(); else off();
}
