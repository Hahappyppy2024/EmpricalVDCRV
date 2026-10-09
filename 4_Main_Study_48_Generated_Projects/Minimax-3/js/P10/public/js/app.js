// Shared browser helpers for the AI Assistant / LLM WebUI.
const AI = (() => {
  const SESSION_STORAGE_KEY = 'aiassist.user';

  function storeUser(user) {
    if (user) localStorage.setItem(SESSION_STORAGE_KEY, JSON.stringify(user));
    else localStorage.removeItem(SESSION_STORAGE_KEY);
  }
  function loadUser() {
    try { return JSON.parse(localStorage.getItem(SESSION_STORAGE_KEY) || 'null'); }
    catch { return null; }
  }

  async function api(method, path, body) {
    const headers = { 'Accept': 'application/json' };
    let payload;
    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
      payload = JSON.stringify(body);
    }
    const res = await fetch(path, { method, headers, body: payload, credentials: 'same-origin' });
    const text = await res.text();
    let data;
    try { data = text ? JSON.parse(text) : {}; }
    catch { data = { ok: false, error: { code: 'bad_response', message: text } }; }
    if (!res.ok || data.ok === false) {
      const err = new Error(data?.error?.message || `HTTP ${res.status}`);
      err.code = data?.error?.code || 'http_error';
      err.status = res.status;
      err.details = data?.error?.details;
      throw err;
    }
    return data.data;
  }

  function el(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (k === 'className') node.className = v;
      else if (k === 'dataset') Object.assign(node.dataset, v);
      else if (k.startsWith('on') && typeof v === 'function') node.addEventListener(k.slice(2), v);
      else if (v === false || v == null) continue;
      else if (v === true) node.setAttribute(k, '');
      else node.setAttribute(k, v);
    }
    for (const child of children.flat()) {
      if (child == null || child === false) continue;
      if (typeof child === 'string' || typeof child === 'number') {
        node.appendChild(document.createTextNode(String(child)));
      } else if (child instanceof Node) {
        node.appendChild(child);
      }
    }
    return node;
  }

  function flash(message, kind = 'info', timeout = 4000) {
    const root = document.querySelector('#flash-root') || (() => {
      const div = document.createElement('div');
      div.id = 'flash-root';
      div.style.position = 'fixed';
      div.style.top = '12px';
      div.style.right = '12px';
      div.style.zIndex = '50';
      document.body.appendChild(div);
      return div;
    })();
    const f = el('div', { className: `flash ${kind}` }, message);
    root.appendChild(f);
    setTimeout(() => f.remove(), timeout);
  }

  async function fetchCurrentUser() {
    try {
      const data = await api('GET', '/api/me');
      storeUser(data.user);
      return data.user;
    } catch (err) {
      storeUser(null);
      return null;
    }
  }

  async function ensureAuth(redirect = '/') {
    const user = loadUser() || await fetchCurrentUser();
    if (!user) {
      window.location.href = redirect;
      return null;
    }
    return user;
  }

  function bindNav() {
    const path = window.location.pathname;
    document.querySelectorAll('nav.main-nav a').forEach(a => {
      if (a.getAttribute('href') === path) a.classList.add('active');
    });
  }

  function signOut() {
    return api('POST', '/api/ai/auth/sign-out').catch(() => {})
      .finally(() => {
        storeUser(null);
        window.location.href = '/';
      });
  }

  return {
    api, el, flash, fetchCurrentUser, ensureAuth, storeUser, loadUser,
    bindNav, signOut
  };
})();

window.AI = AI;
