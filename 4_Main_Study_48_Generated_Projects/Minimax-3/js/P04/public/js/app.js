const HBS = {
  async api(path, opts = {}) {
    const init = Object.assign({ credentials: 'same-origin', headers: {} }, opts);
    if (init.body && typeof init.body !== 'string' && !(init.body instanceof FormData)) {
      init.headers['content-type'] = 'application/json';
      init.body = JSON.stringify(init.body);
    }
    const res = await fetch(path, init);
    const ct = res.headers.get('content-type') || '';
    if (ct.includes('application/json')) {
      const data = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, data };
    }
    const text = await res.text();
    return { ok: res.ok, status: res.status, text, data: null };
  },

  flash(el, kind, message) {
    if (!el) return;
    el.innerHTML = `<div class="flash ${kind}">${escape(message)}</div>`;
    if (kind !== 'error') {
      setTimeout(() => { if (el.innerHTML.includes(message)) el.innerHTML = ''; }, 4000);
    }
  },

  formatCents(cents) {
    if (cents === undefined || cents === null || Number.isNaN(Number(cents))) return '$0.00';
    return '$' + (Number(cents) / 100).toFixed(2);
  },

  formatDate(s) {
    if (!s) return '';
    return s.slice(0, 10);
  },

  async currentUser() {
    const r = await this.api('/api/hotel/account_access');
    return r.ok && r.data && r.data.signed_in ? r.data.user : null;
  },

  async requireRole(roles) {
    const u = await this.currentUser();
    if (!u) { window.location.href = '/pages/account.html'; return null; }
    if (roles && roles.length && !roles.includes(u.role)) {
      document.body.innerHTML = '<main><section class="card"><h2>Access denied</h2><p>Your role (' + u.role + ') cannot view this page.</p><a href="/pages/index.html">Back to home</a></section></main>';
      return null;
    }
    return u;
  },

  setNav(user) {
    const nav = document.querySelector('nav.main-nav');
    if (!nav) return;
    let html = '<a href="/pages/index.html">Home</a>';
    html += '<a href="/pages/rooms.html">Rooms</a>';
    if (user) {
      if (user.role === 'guest') {
        html += '<a href="/pages/bookings.html">My bookings</a>';
        html += '<a href="/pages/messages.html">Messages</a>';
        html += '<a href="/pages/reviews.html">Reviews</a>';
      }
      if (user.role === 'staff') {
        html += '<a href="/pages/staff_check.html">Check-in/out</a>';
        html += '<a href="/pages/inventory.html">Inventory</a>';
        html += '<a href="/pages/messages.html">Messages</a>';
      }
      if (user.role === 'admin') {
        html += '<a href="/pages/inventory.html">Inventory</a>';
        html += '<a href="/pages/admin_reports.html">Reports</a>';
        html += '<a href="/pages/staff_check.html">Check-in/out</a>';
      }
      if (user.role === 'moderator') {
        html += '<a href="/pages/reviews.html">Review moderation</a>';
      }
      html += '<a href="/pages/account.html">Account</a>';
      html += '<span class="user-pill">' + escape(user.display_name) + ' · ' + user.role + '</span>';
      html += '<a href="#" id="logout-link">Sign out</a>';
    } else {
      html += '<a href="/pages/account.html">Sign in</a>';
    }
    nav.innerHTML = html;
    const out = document.getElementById('logout-link');
    if (out) out.addEventListener('click', async (e) => {
      e.preventDefault();
      await HBS.api('/api/hotel/account_access', { method: 'POST', body: { mode: 'logout' } });
      window.location.href = '/pages/index.html';
    });
  }
};

function escape(s) {
  if (s === undefined || s === null) return '';
  return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

window.escape = escape;
window.HBS = HBS;

document.addEventListener('DOMContentLoaded', async () => {
  const user = await HBS.currentUser();
  HBS.setNav(user);
});