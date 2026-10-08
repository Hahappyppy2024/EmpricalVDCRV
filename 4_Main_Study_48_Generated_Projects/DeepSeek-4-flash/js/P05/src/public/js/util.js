export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

export function formatDate(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export function shortDate(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export function toast(message, type = 'info') {
  let wrap = document.querySelector('.toast-wrap');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.className = 'toast-wrap';
    document.body.appendChild(wrap);
  }
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = message;
  wrap.appendChild(el);
  setTimeout(() => el.remove(), 4200);
}

export function statusPill(status) {
  const map = {
    draft: 'pill-draft',
    review: 'pill-review',
    scheduled: 'pill-scheduled',
    published: 'pill-published',
    pending: 'pill-pending',
    approved: 'pill-approved',
    removed: 'pill-removed',
    active: 'pill-active',
    disabled: 'pill-disabled'
  };
  return `<span class="pill ${map[status] || 'pill-draft'}">${escapeHtml(status)}</span>`;
}

export function parseTags(tags) {
  if (Array.isArray(tags)) return tags;
  if (typeof tags === 'string') {
    try {
      const v = JSON.parse(tags);
      return Array.isArray(v) ? v : [];
    } catch {
      return [];
    }
  }
  return [];
}

export function tagChips(tags) {
  return parseTags(tags).map((t) => `<span class="tag">${escapeHtml(t)}</span>`).join('');
}
