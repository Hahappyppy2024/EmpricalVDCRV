/* Shared frontend helpers. */

function escapeHtml(value) {
  return String(value == null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function fmtTime(isoString) {
  if (!isoString) return '';
  const date = new Date(isoString);
  if (Number.isNaN(date.getTime())) return isoString;
  return date.toLocaleString();
}

function fmtDate(isoString) {
  if (!isoString) return '';
  const date = new Date(isoString);
  if (Number.isNaN(date.getTime())) return isoString;
  return date.toLocaleDateString();
}

async function apiFetch(url, options = {}) {
  const headers = Object.assign({}, options.headers || {});
  if (options.body && !(options.body instanceof FormData) && typeof options.body !== 'string') {
    headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(options.body);
  }
  const response = await fetch(url, Object.assign({}, options, { headers }));
  let payload = null;
  try {
    payload = await response.json();
  } catch (_) {
    payload = { ok: false, error: { code: 'http_' + response.status, message: response.statusText } };
  }
  return { response, payload };
}

function userLabel(user) {
  if (!user) return 'unknown';
  return user.display_name || user.username;
}

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined && text !== null) node.textContent = text;
  return node;
}
