(function () {
  const root = document.getElementById('share-root');
  const token = window.location.pathname.split('/').filter(Boolean).pop();

  function esc(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function fmt(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return String(iso).replace('T', ' ').slice(0, 19);
    return d.toLocaleString();
  }

  fetch(`/api/share/${encodeURIComponent(token)}`, { credentials: 'same-origin' })
    .then((res) => res.json())
    .then((data) => {
      if (!data.ok) throw new Error(data.error?.message || 'Share link not found');
      root.innerHTML = `
        <h1 style="margin:0 0 4px;font-size:20px">${esc(data.conversation.title)}</h1>
        <p class="auth-subtitle">Shared by <strong>${esc(data.owner?.username || 'unknown')}</strong> · created ${fmt(data.share.created_at)}</p>
        <div class="panel" style="border:0;background:var(--bg-soft)">
          ${data.messages.length
            ? data.messages
                .map(
                  (m) => `
                  <div class="msg ${esc(m.role)}" style="max-width:92%;margin:10px 0">
                    ${esc(m.content)}
                    ${m.citations?.length ? `<div class="cite">${m.citations.map((c, i) => `[${i + 1}] ${esc(c.file)} — ${esc(c.snippet)}`).join('<br>')}</div>` : ''}
                  </div>`
                )
                .join('')
            : '<div class="empty">This conversation has no messages.</div>'}
        </div>
        <p class="auth-hint">Public read-only share link. ${data.share.expires_at ? `Expires ${fmt(data.share.expires_at)}.` : 'No expiry.'}</p>`;
    })
    .catch((err) => {
      root.innerHTML = `<div class="error-box">${esc(err.message || 'Could not load this share link')}</div>`;
    });
})();
