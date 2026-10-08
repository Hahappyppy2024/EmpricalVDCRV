import { api } from './api.js';
import { escapeHtml, formatDate, shortDate, toast, statusPill, tagChips } from './util.js';
import { createRichTextEditor, renderPreview } from './richtext.js';

let me = null;
let viewEl = null;
let titleEl = null;
let actionsEl = null;
let ws = null;
let wsRetry = 0;

const isStaff = () => ['editor', 'admin'].includes(me.role);
const isAdmin = () => me.role === 'admin';
const isAuthor = () => me.role === 'author';
const canWrite = () => ['author', 'editor', 'admin'].includes(me.role);

function navGroup(label) {
  return `<div class="nav-group">${escapeHtml(label)}</div>`;
}
function navItem(label, hash) {
  const active = window.location.hash.startsWith(hash) ? ' active' : '';
  return `<a href="${hash}" class="${active}">${escapeHtml(label)}</a>`;
}

function renderNav() {
  const nav = document.getElementById('side-nav');
  let html = navGroup('Overview');
  html += navItem('Dashboard', '#/dashboard');
  html += navItem('Account access', '#/account-access');

  if (canWrite()) {
    html += navGroup('Content');
    html += navItem('Content', '#/content');
    html += navItem('Media library', '#/media');
    html += navItem('Publishing workflow', '#/publishing');
  }

  if (isStaff()) {
    html += navGroup('Site');
    html += navItem('Page templates', '#/templates');
  }

  if (isAdmin()) {
    html += navGroup('Administration');
    html += navItem('Users & roles', '#/admin/users');
    html += navItem('Plugin settings', '#/admin/settings');
    html += navItem('Import / export', '#/admin/import-export');
  }

  html += navGroup('Tools');
  html += navItem('API diagnostics', '#/diagnostics');

  nav.innerHTML = html;
}

async function boot() {
  try {
    const res = await api('/api/auth/me');
    me = res.data.user;
  } catch {
    window.location.href = '/login';
    return;
  }

  document.getElementById('side-user-name').textContent = me.displayName || me.username;
  document.getElementById('side-user-role').textContent = me.role;
  viewEl = document.getElementById('view');
  titleEl = document.getElementById('page-title');
  actionsEl = document.getElementById('page-actions');

  document.getElementById('logout-btn').addEventListener('click', async () => {
    try { await api('/api/auth/logout', { method: 'POST' }); } catch { /* ignore */ }
    window.location.href = '/';
  });

  connectWs();
  renderNav();
  window.addEventListener('hashchange', route);
  route();
}

function connectWs() {
  const proto = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
  try {
    ws = new WebSocket(`${proto}//${window.location.host}/ws`);
  } catch {
    return;
  }
  ws.addEventListener('open', () => { wsRetry = 0; });
  ws.addEventListener('message', (event) => {
    try {
      const msg = JSON.parse(event.data);
      handleWs(msg);
    } catch { /* ignore malformed */ }
  });
  ws.addEventListener('close', () => {
    setTimeout(() => {
      wsRetry += 1;
      if (wsRetry < 5) connectWs();
    }, 3000 * (wsRetry + 1));
  });
}

function handleWs(msg) {
  const hash = window.location.hash;
  if (msg.type === 'publishing:transition' || msg.type === 'content:status') {
    if (hash.startsWith('#/publishing') || hash.startsWith('#/dashboard')) route();
    else toast(`Publishing: ${msg.data.articleTitle} → ${msg.data.toStatus}`, 'success');
  }
  if (msg.type === 'comment:new' && msg.data.status === 'pending') {
    toast('A new comment is awaiting moderation.', 'warning');
  }
  if (msg.type === 'comment:moderated') {
    if (hash === '#/publishing' || hash === '#/content') route();
  }
  if (msg.type === 'settings:updated') {
    toast(`Setting "${msg.data.key}" updated.`, 'info');
  }
  if (msg.type === 'import_export:done') {
    toast('Import/export job completed.', 'success');
  }
  if (msg.type === 'media:uploaded' || msg.type === 'media:deleted') {
    if (hash.startsWith('#/media')) route();
  }
}

function setTitle(text) {
  titleEl.textContent = text;
  document.title = `${text} · P05 CMS`;
}
function setActions(html) {
  actionsEl.innerHTML = html || '';
}

function route() {
  const hash = window.location.hash || '#/dashboard';
  const parts = hash.slice(1).split('/').filter(Boolean); // ['content','edit','5']

  if (parts.length === 0 || parts[0] === 'dashboard') return viewDashboard();
  if (parts[0] === 'account-access') return viewAccountAccess();
  if (parts[0] === 'content' && parts.length === 1) return viewContent();
  if (parts[0] === 'content' && parts[1] === 'new') return viewEditor(null);
  if (parts[0] === 'content' && parts[1] === 'edit') return viewEditor(Number(parts[2]));
  if (parts[0] === 'media') return viewMedia();
  if (parts[0] === 'publishing') return viewPublishing();
  if (parts[0] === 'templates') return viewTemplates();
  if (parts[0] === 'admin' && parts[1] === 'users') return viewUsers();
  if (parts[0] === 'admin' && parts[1] === 'settings') return viewSettings();
  if (parts[0] === 'admin' && parts[1] === 'import-export') return viewImportExport();
  if (parts[0] === 'diagnostics') return viewDiagnostics();
  return viewDashboard();
}

function guard(roles, fallbackHash = '#/dashboard') {
  if (!roles.includes(me.role)) {
    window.location.hash = fallbackHash;
    return false;
  }
  return true;
}

// ---------------------------------------------------------------- Dashboard
async function viewDashboard() {
  setTitle('Dashboard');
  setActions(`<a class="btn btn-sm" href="#/content/new">+ New article</a>`);

  const [contentRes, pendingRes, mediaRes, queueRes] = await Promise.all([
    api('/api/cms/content_authoring?pageSize=200').catch(() => null),
    api('/api/cms/comments?status=pending').catch(() => null),
    api('/api/cms/media_library?pageSize=200').catch(() => null),
    api('/api/cms/publishing_workflow/queue').catch(() => null)
  ]);

  const articles = (contentRes && contentRes.data) || [];
  const counts = { draft: 0, review: 0, scheduled: 0, published: 0 };
  for (const a of articles) if (counts[a.status] !== undefined) counts[a.status] += 1;
  const showModeration = ['moderator', 'editor', 'admin'].includes(me.role);
  const pending = showModeration ? (pendingRes && pendingRes.data) || [] : [];
  const mediaCount = (mediaRes && mediaRes.meta && mediaRes.meta.total) || 0;
  const queue = showModeration ? (queueRes && queueRes.data) || [] : [];

  viewEl.innerHTML = `
    <div class="stat-grid">
      <div class="stat-card"><div class="num">${articles.length}</div><div class="lbl">Articles</div></div>
      <div class="stat-card"><div class="num">${counts.published}</div><div class="lbl">Published</div></div>
      <div class="stat-card"><div class="num">${counts.review + counts.scheduled}</div><div class="lbl">In review / scheduled</div></div>
      ${showModeration ? `<div class="stat-card"><div class="num">${pending.length}</div><div class="lbl">Comments pending</div></div>` : ''}
      <div class="stat-card"><div class="num">${mediaCount}</div><div class="lbl">Media assets</div></div>
    </div>

    <div class="card">
      <h2>Recent content</h2>
      ${articles.length === 0 ? '<div class="empty">No content yet.</div>' : `
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Title</th><th>Status</th><th>Author</th><th>Category</th><th>Updated</th><th></th></tr></thead>
        <tbody>
          ${articles.slice(0, 8).map((a) => `
            <tr>
              <td><a href="#/content/edit/${a.id}">${escapeHtml(a.title)}</a></td>
              <td>${statusPill(a.status)}</td>
              <td>${escapeHtml(a.author_username || '')}</td>
              <td>${escapeHtml(a.category_name || '—')}</td>
              <td>${shortDate(a.updated_at)}</td>
              <td><a class="btn btn-sm btn-secondary" href="#/content/edit/${a.id}">Edit</a></td>
            </tr>`).join('')}
        </tbody>
      </table></div>`}
    </div>

    ${showModeration ? `
    <div class="card">
      <h2>Pending comments</h2>
      ${pending.length === 0 ? '<div class="empty">No comments awaiting moderation.</div>' : `
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Article</th><th>Author</th><th>Body</th><th></th></tr></thead>
        <tbody>
          ${pending.slice(0, 6).map((c) => `
            <tr>
              <td>${escapeHtml(c.article_title || '—')}</td>
              <td>${escapeHtml(c.author_name)}</td>
              <td style="max-width:280px;">${escapeHtml(c.body)}</td>
              <td><button class="btn btn-sm btn-success" data-approve-comment="${c.id}">Approve</button></td>
            </tr>`).join('')}
        </tbody>
      </table></div>`}
    </div>

    ${isStaff() ? `
    <div class="card">
      <h2>Publishing queue (review / scheduled)</h2>
      ${queue.length === 0 ? '<div class="empty">Queue is empty.</div>' : `
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Title</th><th>Status</th><th>Scheduled for</th><th></th></tr></thead>
        <tbody>
          ${queue.slice(0, 6).map((a) => `
            <tr>
              <td><a href="#/publishing">${escapeHtml(a.title)}</a></td>
              <td>${statusPill(a.status)}</td>
              <td>${shortDate(a.publish_at)}</td>
              <td><a class="btn btn-sm btn-secondary" href="#/publishing">Manage</a></td>
            </tr>`).join('')}
        </tbody>
      </table></div>`}
    </div>` : ''}
  `;

  viewEl.querySelectorAll('[data-approve-comment]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        await api(`/api/cms/comments/${btn.dataset.approveComment}`, { method: 'PATCH', body: { status: 'approved' } });
        toast('Comment approved.', 'success');
        viewDashboard();
      } catch (err) { toast(err.message, 'error'); }
    });
  });
}

// ------------------------------------------------------------- Account access
async function viewAccountAccess() {
  setTitle('Account access');
  setActions('');
  const res = await api('/api/cms/account_access?pageSize=100');
  const rows = res.data || [];

  viewEl.innerHTML = `
    <div class="card">
      <h2>Sign-in activity</h2>
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>User</th><th>Action</th><th>Details</th><th>IP</th><th>When</th></tr></thead>
        <tbody>
          ${rows.length === 0 ? '<tr><td colspan="5"><div class="empty">No account access events.</div></td></tr>' : rows.map((r) => `
            <tr>
              <td>${escapeHtml(r.username || '—')}</td>
              <td>${statusPill(r.action)}</td>
              <td>${escapeHtml(r.details || '')}</td>
              <td>${escapeHtml(r.ip || '—')}</td>
              <td>${formatDate(r.created_at)}</td>
            </tr>`).join('')}
        </tbody>
      </table></div>
    </div>
    <div class="card">
      <h2>Record an access event</h2>
      <form id="access-form" style="max-width:480px;">
        <div class="field">
          <label for="access-action">Action</label>
          <select id="access-action">
            <option value="signin">signin</option>
            <option value="signout">signout</option>
            <option value="signup">signup</option>
            <option value="reset">reset</option>
            <option value="other">other</option>
          </select>
        </div>
        <div class="field">
          <label for="access-details">Details</label>
          <input id="access-details" maxlength="500" placeholder="Optional note">
        </div>
        <button class="btn" type="submit">Record event</button>
      </form>
    </div>
  `;

  document.getElementById('access-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      await api('/api/cms/account_access', {
        method: 'POST',
        body: { action: document.getElementById('access-action').value, details: document.getElementById('access-details').value || null }
      });
      toast('Access event recorded.', 'success');
      viewAccountAccess();
    } catch (err) { toast(err.message, 'error'); }
  });
}

// ------------------------------------------------------------- Content list
async function viewContent() {
  if (!canWrite()) return guard(['author', 'editor', 'admin']);
  setTitle('Content');
  setActions(`<a class="btn btn-sm" href="#/content/new">+ New article</a>`);

  const res = await api('/api/cms/content_authoring?pageSize=200');
  const articles = res.data || [];

  viewEl.innerHTML = `
    <div class="card">
      <div class="field" style="max-width:260px; margin-left:auto; margin-bottom:12px;">
        <select id="status-filter">
          <option value="">All statuses</option>
          <option value="draft">Draft</option>
          <option value="review">In review</option>
          <option value="scheduled">Scheduled</option>
          <option value="published">Published</option>
        </select>
      </div>
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Title</th><th>Status</th><th>Author</th><th>Category</th><th>Tags</th><th>Updated</th><th></th></tr></thead>
        <tbody id="content-rows">
          ${articles.length === 0 ? '<tr><td colspan="7"><div class="empty">No content yet. Create your first article.</div></td></tr>' : articles.map((a) => `
            <tr data-status="${escapeHtml(a.status)}">
              <td><a href="#/content/edit/${a.id}">${escapeHtml(a.title)}</a></td>
              <td>${statusPill(a.status)}</td>
              <td>${escapeHtml(a.author_username || '')}</td>
              <td>${escapeHtml(a.category_name || '—')}</td>
              <td>${tagChips(a.tags)}</td>
              <td>${shortDate(a.updated_at)}</td>
              <td>
                <a class="btn btn-sm btn-secondary" href="#/content/edit/${a.id}">Edit</a>
                ${a.status !== 'published' ? `<button class="btn btn-sm btn-danger" data-delete-article="${a.id}">Delete</button>` : ''}
              </td>
            </tr>`).join('')}
        </tbody>
      </table></div>
    </div>
  `;

  document.getElementById('status-filter').addEventListener('change', (e) => {
    const val = e.target.value;
    document.querySelectorAll('#content-rows tr').forEach((tr) => {
      tr.style.display = (!val || tr.dataset.status === val) ? '' : 'none';
    });
  });

  viewEl.querySelectorAll('[data-delete-article]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!window.confirm('Delete this article permanently?')) return;
      try {
        await api(`/api/cms/content_authoring/${btn.dataset.deleteArticle}`, { method: 'DELETE' });
        toast('Article deleted.', 'success');
        viewContent();
      } catch (err) { toast(err.message, 'error'); }
    });
  });
}

// ------------------------------------------------------------- Editor (CMS-02 + CMS-03)
async function viewEditor(articleId) {
  if (!canWrite()) return guard(['author', 'editor', 'admin']);

  let article = null;
  let categories = [];
  let templates = [];
  let history = [];

  const [catRes] = await Promise.all([
    api('/api/public/categories').catch(() => null),
  ]);
  categories = (catRes && catRes.data) || [];

  if (articleId) {
    const artRes = await api(`/api/cms/content_authoring/${articleId}`).catch(() => null);
    article = artRes ? artRes.data : null;
    if (!article) {
      window.location.hash = '#/content';
      return;
    }
    const histRes = await api(`/api/cms/rich_text_editor?articleId=${articleId}&pageSize=50`).catch(() => null);
    history = (histRes && histRes.data) || [];
  }
  if (isStaff()) {
    const tplRes = await api('/api/cms/page_templates').catch(() => null);
    templates = (tplRes && tplRes.data && tplRes.data.templates) || [];
  }

  const isEdit = !!article;
  setTitle(isEdit ? `Edit: ${article.title}` : 'New article');
  setActions(isEdit ? `<a class="btn btn-sm btn-secondary" href="/article/${escapeHtml(article.slug)}" target="_blank">View live ↗</a>` : '');

  viewEl.innerHTML = `
    <form id="article-form">
      <div class="card">
        <div class="field">
          <label for="art-title">Title</label>
          <input id="art-title" value="${isEdit ? escapeHtml(article.title) : ''}" maxlength="200" required>
        </div>
        <div class="field-row" style="display:flex; gap:14px; flex-wrap:wrap;">
          <div class="field" style="flex:1;">
            <label for="art-category">Category</label>
            <select id="art-category">
              <option value="">None</option>
              ${categories.map((c) => `<option value="${c.id}" ${isEdit && article.category_id === c.id ? 'selected' : ''}>${escapeHtml(c.name)}</option>`).join('')}
            </select>
          </div>
          ${isStaff() ? `
          <div class="field" style="flex:1;">
            <label for="art-template">Page template</label>
            <select id="art-template">
              <option value="">Default</option>
              ${templates.map((t) => `<option value="${t.id}" ${isEdit && article.template_id === t.id ? 'selected' : ''}>${escapeHtml(t.name)}</option>`).join('')}
            </select>
          </div>` : ''}
        </div>
        <div class="field">
          <label for="art-tags">Tags <span class="hint">(comma separated)</span></label>
          <input id="art-tags" value="${isEdit ? (article.tags || []).join(', ') : ''}" maxlength="300">
        </div>
        <div class="field">
          <label>Rich text body <span class="hint">(use the toolbar to format)</span></label>
          <div id="rte-container"></div>
        </div>
        <div class="actions">
          <button class="btn" type="submit" id="save-btn">${isEdit ? 'Save changes' : 'Create article'}</button>
          <button class="btn btn-secondary" type="button" id="preview-toggle">Preview</button>
          <a class="btn btn-ghost" href="#/content">Cancel</a>
        </div>
      </div>
    </form>

    ${isEdit ? `
    <div class="card" id="workflow-card">
      <h2>Publishing workflow</h2>
      <p>Status: ${statusPill(article.status)}</p>
      <div class="actions" id="workflow-actions"></div>
    </div>
    <div class="card">
      <h2>Rich text versions (${history.length})</h2>
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Version</th><th>Updated by</th><th>When</th><th>Preview</th></tr></thead>
        <tbody>
          ${history.slice(0, 12).map((h) => `
            <tr>
              <td>v${h.version}</td>
              <td>${escapeHtml(h.updated_by_username || '')}</td>
              <td>${formatDate(h.updated_at)}</td>
              <td><button class="btn btn-sm btn-secondary" data-preview-version="${h.id}">Preview</button></td>
            </tr>`).join('')}
        </tbody>
      </table></div>
    </div>` : ''}

    <div id="preview-panel" style="display:none;" class="card">
      <h2>Preview</h2>
      <div class="rte-preview" id="preview-body"></div>
    </div>
  `;

  const editor = createRichTextEditor(document.getElementById('rte-container'), isEdit ? (article.body_html || '') : '', {
    onChange: () => {
      const saveBtn = document.getElementById('save-btn');
      if (saveBtn) saveBtn.textContent = isEdit ? 'Save changes' : 'Create article';
    },
    onInsertMedia: (insertCallback) => openMediaPicker(insertCallback)
  });

  document.getElementById('preview-toggle').addEventListener('click', () => {
    const panel = document.getElementById('preview-panel');
    panel.style.display = panel.style.display === 'none' ? '' : 'none';
    renderPreview(document.getElementById('preview-body'), editor.getHtml());
  });

  if (isEdit) {
    viewEl.querySelectorAll('[data-preview-version]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const doc = history.find((h) => h.id === Number(btn.dataset.previewVersion));
        if (!doc) return;
        document.getElementById('preview-panel').style.display = '';
        renderPreview(document.getElementById('preview-body'), doc.content_html);
      });
    });

    const wfActions = document.getElementById('workflow-actions');
    const canAuthorSubmit = isAuthor() && article.author_id === me.id && article.status === 'draft';
    const canDecide = isStaff() && ['review', 'scheduled', 'published'].includes(article.status);
    const buttons = [];
    if (canAuthorSubmit) {
      buttons.push(`<button class="btn btn-warning" data-transition="review">Submit for review</button>`);
    }
    if (canDecide && article.status === 'review') {
      buttons.push(`<button class="btn btn-success" data-transition="published">Approve & publish</button>`);
      buttons.push(`<button class="btn" data-transition="scheduled">Schedule…</button>`);
      buttons.push(`<button class="btn btn-secondary" data-transition="draft">Request changes</button>`);
    }
    if (canDecide && article.status === 'scheduled') {
      buttons.push(`<button class="btn btn-success" data-transition="published">Publish now</button>`);
      buttons.push(`<button class="btn btn-secondary" data-transition="review">Back to review</button>`);
    }
    if (canDecide && article.status === 'published') {
      buttons.push(`<button class="btn btn-secondary" data-transition="draft">Unpublish</button>`);
    }
    wfActions.innerHTML = buttons.join('');

    if (article.status === 'review' && isStaff()) {
      wfActions.insertAdjacentHTML('beforeend', `
        <span style="align-self:center; color:var(--muted); font-size:0.9rem;">Schedule date:
          <input type="datetime-local" id="schedule-at" style="padding:5px; border:1px solid var(--border); border-radius:6px;">
        </span>`);
    }

    wfActions.querySelectorAll('[data-transition]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const toStatus = btn.dataset.transition;
        const note = window.prompt(`Add an optional note for the transition to "${toStatus}":`) || '';
        const body = { articleId: article.id, toStatus, note: note || null };
        if (toStatus === 'scheduled') {
          const dt = document.getElementById('schedule-at') ? document.getElementById('schedule-at').value : '';
          if (!dt) { toast('A schedule date is required.', 'error'); return; }
          body.publishAt = new Date(dt).toISOString().slice(0, 19).replace('T', ' ');
          await api(`/api/cms/content_authoring/${article.id}`, { method: 'PATCH', body: { publishAt: body.publishAt } });
        }
        try {
          await api('/api/cms/publishing_workflow', { method: 'POST', body });
          toast('Workflow transition applied.', 'success');
          viewEditor(article.id);
        } catch (err) { toast(err.message, 'error'); }
      });
    });
  }

  document.getElementById('article-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = {
      title: document.getElementById('art-title').value.trim(),
      body: textOfHtml(editor.getHtml()),
      bodyHtml: editor.getHtml(),
      tags: document.getElementById('art-tags').value.split(',').map((t) => t.trim()).filter(Boolean),
      categoryId: document.getElementById('art-category').value ? Number(document.getElementById('art-category').value) : null
    };
    if (document.getElementById('art-template')) {
      body.templateId = document.getElementById('art-template').value ? Number(document.getElementById('art-template').value) : null;
    }
    try {
      const btn = document.getElementById('save-btn');
      btn.disabled = true;
      btn.textContent = 'Saving…';
      if (isEdit) {
        await api(`/api/cms/content_authoring/${article.id}`, { method: 'PATCH', body });
      } else {
        const created = await api('/api/cms/content_authoring', { method: 'POST', body });
        window.location.hash = `#/content/edit/${created.data.id}`;
        return;
      }
      toast('Article saved.', 'success');
      btn.disabled = false;
      btn.textContent = 'Save changes';
    } catch (err) {
      toast(err.message, 'error');
      const btn = document.getElementById('save-btn');
      btn.disabled = false;
      btn.textContent = isEdit ? 'Save changes' : 'Create article';
    }
  });
}

function textOfHtml(html) {
  const div = document.createElement('div');
  div.innerHTML = html;
  return (div.textContent || '').trim();
}

async function openMediaPicker(insertCallback) {
  const res = await api('/api/cms/media_library?pageSize=200');
  const assets = res.data || [];
  const backdrop = document.createElement('div');
  backdrop.className = 'modal-backdrop';
  backdrop.innerHTML = `
    <div class="modal">
      <button class="modal-close" type="button">✕</button>
      <h2>Insert media</h2>
      <p class="hint" style="color:var(--muted); margin-top:-10px;">Choose an asset to insert into the article body.</p>
      <div class="media-grid">
        ${assets.length === 0 ? '<div class="empty">No media available. Upload some in the media library.</div>' : assets.map((m) => `
          <div class="media-item">
            ${isImage(m.mime_type) ? `<img src="${escapeHtml(m.url)}" alt="${escapeHtml(m.alt_text || '')}">` : `<div class="file-icon" style="display:flex;align-items:center;justify-content:center;background:#f1f5f9;border-radius:6px;">📄</div>`}
            <div class="media-name">${escapeHtml(m.original_name)}</div>
            <div class="media-actions">
              <button class="btn btn-sm btn-secondary" data-insert="${m.id}" data-url="${escapeHtml(m.url)}" data-alt="${escapeHtml(m.alt_text || m.original_name)}">Insert</button>
            </div>
          </div>`).join('')}
      </div>
    </div>
  `;
  document.body.appendChild(backdrop);
  backdrop.addEventListener('click', (e) => { if (e.target === backdrop || e.target.classList.contains('modal-close')) backdrop.remove(); });
  backdrop.querySelectorAll('[data-insert]').forEach((btn) => {
    btn.addEventListener('click', () => {
      insertCallback(btn.dataset.url, btn.dataset.alt);
      backdrop.remove();
    });
  });
}

function isImage(mime) {
  return String(mime || '').startsWith('image/');
}

// ------------------------------------------------------------- Media library
async function viewMedia() {
  if (!canWrite()) return guard(['author', 'editor', 'admin']);
  setTitle('Media library');
  setActions('');

  const res = await api('/api/cms/media_library?pageSize=200');
  const assets = res.data || [];

  viewEl.innerHTML = `
    <div class="card">
      <h2>Upload media</h2>
      <form id="upload-form" style="max-width:520px;">
        <div class="field"><label for="up-file">File</label><input id="up-file" type="file" required></div>
        <div class="field"><label for="up-alt">Alt text</label><input id="up-alt" maxlength="300" placeholder="Describe the asset"></div>
        <div class="field">
          <label for="up-vis">Visibility</label>
          <select id="up-vis"><option value="public">Public</option><option value="private">Private</option></select>
          <div class="hint">Private assets are only visible to you and admins.</div>
        </div>
        <button class="btn" type="submit">Upload</button>
      </form>
    </div>

    <div class="card">
      <h2>Assets (${assets.length})</h2>
      ${assets.length === 0 ? '<div class="empty">No media yet.</div>' : `
      <div class="media-grid">
        ${assets.map((m) => `
          <div class="media-item">
            ${isImage(m.mime_type) ? `<img src="${escapeHtml(m.url)}" alt="${escapeHtml(m.alt_text || '')}">` : `<div class="file-icon" style="display:flex;align-items:center;justify-content:center;background:#f1f5f9;border-radius:6px;height:90px;">📄</div>`}
            <div class="media-name">${escapeHtml(m.original_name)}</div>
            <div style="font-size:0.75rem;color:var(--muted);">${Math.round(m.size_bytes / 1024)} KB · ${statusPill(m.visibility)}</div>
            <div class="media-actions">
              <button class="btn btn-sm btn-secondary" data-copy-url="${escapeHtml(m.url)}">URL</button>
              <button class="btn btn-sm btn-secondary" data-rename="${m.id}" data-name="${escapeHtml(m.original_name)}" data-alt="${escapeHtml(m.alt_text || '')}">Rename</button>
              <button class="btn btn-sm btn-secondary" data-toggle-vis="${m.id}" data-vis="${m.visibility}">${m.visibility === 'public' ? 'Make private' : 'Make public'}</button>
              <button class="btn btn-sm btn-danger" data-delete-media="${m.id}">Delete</button>
            </div>
          </div>`).join('')}
      </div>`}
    </div>
  `;

  document.getElementById('upload-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const file = document.getElementById('up-file').files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('altText', document.getElementById('up-alt').value);
    fd.append('visibility', document.getElementById('up-vis').value);
    try {
      await api('/api/cms/media_library', { method: 'POST', body: fd });
      toast('File uploaded.', 'success');
      viewMedia();
    } catch (err) { toast(err.message, 'error'); }
  });

  viewEl.querySelectorAll('[data-copy-url]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(btn.dataset.copyUrl);
        toast('URL copied to clipboard.', 'success');
      } catch {
        toast(`URL: ${btn.dataset.copyUrl}`, 'info');
      }
    });
  });

  viewEl.querySelectorAll('[data-delete-media]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!window.confirm('Delete this media asset permanently?')) return;
      try {
        await api(`/api/cms/media_library/${btn.dataset.deleteMedia}`, { method: 'DELETE' });
        toast('Media deleted.', 'success');
        viewMedia();
      } catch (err) { toast(err.message, 'error'); }
    });
  });

  viewEl.querySelectorAll('[data-toggle-vis]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const next = btn.dataset.vis === 'public' ? 'private' : 'public';
      try {
        await api(`/api/cms/media_library/${btn.dataset.toggleVis}`, { method: 'PATCH', body: { visibility: next } });
        toast('Visibility updated.', 'success');
        viewMedia();
      } catch (err) { toast(err.message, 'error'); }
    });
  });

  viewEl.querySelectorAll('[data-rename]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const name = window.prompt('File name:', btn.dataset.name);
      if (!name) return;
      const alt = window.prompt('Alt text:', btn.dataset.alt) ?? btn.dataset.alt;
      try {
        await api(`/api/cms/media_library/${btn.dataset.rename}`, { method: 'PATCH', body: { originalName: name.trim(), altText: alt.trim() } });
        toast('Media updated.', 'success');
        viewMedia();
      } catch (err) { toast(err.message, 'error'); }
    });
  });
}

// ------------------------------------------------------------- Publishing workflow
async function viewPublishing() {
  if (!canWrite()) return guard(['author', 'editor', 'admin']);
  setTitle('Publishing workflow');
  setActions('');

  const [queueRes, histRes] = await Promise.all([
    isStaff()
      ? api('/api/cms/publishing_workflow/queue').catch(() => null)
      : api('/api/cms/content_authoring?pageSize=200').catch(() => null),
    api('/api/cms/publishing_workflow?pageSize=100').catch(() => null)
  ]);
  let queue = [];
  if (isStaff()) {
    queue = (queueRes && queueRes.data) || [];
  } else {
    const mine = (queueRes && queueRes.data) || [];
    queue = mine.filter((a) => ['review', 'scheduled'].includes(a.status));
  }
  const history = (histRes && histRes.data) || [];
  const showQueue = isStaff();

  viewEl.innerHTML = `
    ${showQueue ? `
    <div class="card">
      <h2>Queue (review / scheduled) — live updates via WebSocket</h2>
      <div id="publishing-queue">
        ${queue.length === 0 ? '<div class="empty">No articles awaiting a decision.</div>' : `
        <div class="table-wrap"><table class="tbl">
          <thead><tr><th>Title</th><th>Status</th><th>Author</th><th>Category</th><th>Pending comments</th><th>Scheduled for</th><th>Actions</th></tr></thead>
          <tbody>
            ${queue.map((a) => `
              <tr>
                <td><a href="#/content/edit/${a.id}">${escapeHtml(a.title)}</a></td>
                <td>${statusPill(a.status)}</td>
                <td>${escapeHtml(a.author_username || '')}</td>
                <td>${escapeHtml(a.category_name || '—')}</td>
                <td>${a.pending_comments}</td>
                <td>${shortDate(a.publish_at)}</td>
                <td>
                  ${a.status === 'review' ? `
                    <button class="btn btn-sm btn-success" data-pub-publish="${a.id}">Publish</button>
                    <button class="btn btn-sm" data-pub-schedule="${a.id}">Schedule…</button>
                    <button class="btn btn-sm btn-secondary" data-pub-draft="${a.id}">Request changes</button>` : ''}
                  ${a.status === 'scheduled' ? `
                    <button class="btn btn-sm btn-success" data-pub-publish="${a.id}">Publish now</button>
                    <button class="btn btn-sm btn-secondary" data-pub-review="${a.id}">Back to review</button>` : ''}
                </td>
              </tr>`).join('')}
          </tbody>
        </table></div>`}
      </div>
    </div>` : `<div class="card"><h2>Your articles in the workflow</h2>
      ${queue.length === 0 ? '<div class="empty">No articles currently in review or scheduled.</div>' : `
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Title</th><th>Status</th><th>Scheduled for</th><th></th></tr></thead>
        <tbody>${queue.map((a) => `<tr><td>${escapeHtml(a.title)}</td><td>${statusPill(a.status)}</td><td>${shortDate(a.publish_at)}</td><td><a class="btn btn-sm btn-secondary" href="#/content/edit/${a.id}">Open</a></td></tr>`).join('')}</tbody>
      </table></div>`}</div>`}

    <div class="card">
      <h2>Workflow history</h2>
      ${history.length === 0 ? '<div class="empty">No workflow activity yet.</div>' : `
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Article</th><th>From</th><th>To</th><th>Actor</th><th>Note</th><th>When</th></tr></thead>
        <tbody>
          ${history.slice(0, 40).map((h) => `
            <tr>
              <td><a href="#/content/edit/${h.article_id}">${escapeHtml(h.article_title)}</a></td>
              <td>${statusPill(h.from_status || '—')}</td>
              <td>${statusPill(h.to_status)}</td>
              <td>${escapeHtml(h.actor_username || '')}</td>
              <td style="max-width:240px;">${escapeHtml(h.note || '')}</td>
              <td>${formatDate(h.created_at)}</td>
            </tr>`).join('')}
        </tbody>
      </table></div>`}
    </div>
  `;

  viewEl.querySelectorAll('[data-pub-publish]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        await api('/api/cms/publishing_workflow', { method: 'POST', body: { articleId: Number(btn.dataset.pubPublish), toStatus: 'published', note: 'Published from queue' } });
        toast('Article published.', 'success');
        viewPublishing();
      } catch (err) { toast(err.message, 'error'); }
    });
  });
  viewEl.querySelectorAll('[data-pub-draft]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const note = window.prompt('Reason for sending back:') || 'Changes requested';
      try {
        await api('/api/cms/publishing_workflow', { method: 'POST', body: { articleId: Number(btn.dataset.pubDraft), toStatus: 'draft', note } });
        toast('Sent back to draft.', 'success');
        viewPublishing();
      } catch (err) { toast(err.message, 'error'); }
    });
  });
  viewEl.querySelectorAll('[data-pub-review]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        await api('/api/cms/publishing_workflow', { method: 'POST', body: { articleId: Number(btn.dataset.pubReview), toStatus: 'review', note: 'Moved back to review' } });
        toast('Moved back to review.', 'success');
        viewPublishing();
      } catch (err) { toast(err.message, 'error'); }
    });
  });
  viewEl.querySelectorAll('[data-pub-schedule]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const dt = window.prompt('Schedule publish date/time (YYYY-MM-DD HH:MM):');
      if (!dt) return;
      const normalized = new Date(dt.replace(' ', 'T')).toISOString().slice(0, 19).replace('T', ' ');
      if (Number.isNaN(Date.parse(normalized.replace(' ', 'T')))) { toast('Invalid date.', 'error'); return; }
      try {
        const id = Number(btn.dataset.pubSchedule);
        await api(`/api/cms/content_authoring/${id}`, { method: 'PATCH', body: { publishAt: normalized } });
        await api('/api/cms/publishing_workflow', { method: 'POST', body: { articleId: id, toStatus: 'scheduled', note: `Scheduled for ${normalized}` } });
        toast('Article scheduled.', 'success');
        viewPublishing();
      } catch (err) { toast(err.message, 'error'); }
    });
  });
}

// ------------------------------------------------------------- Templates (CMS-08)
async function viewTemplates() {
  if (!isStaff()) return guard(['editor', 'admin']);
  setTitle('Page templates');
  setActions('');

  const res = await api('/api/cms/page_templates');
  const { templates, menus } = res.data;

  viewEl.innerHTML = `
    <div class="card">
      <h2>Create template</h2>
      <form id="tpl-form" style="max-width:520px;">
        <div class="field"><label for="tpl-name">Name</label><input id="tpl-name" maxlength="100" required></div>
        <div class="field"><label for="tpl-slug">Slug <span class="hint">(optional)</span></label><input id="tpl-slug" maxlength="100" placeholder="auto-generated"></div>
        <div class="field"><label for="tpl-desc">Description</label><input id="tpl-desc" maxlength="300"></div>
        <div class="field"><label for="tpl-body">Template body</label><textarea id="tpl-body" rows="3" placeholder="{{title}} {{body}} {{author}} {{date}} {{related}}"></textarea></div>
        <button class="btn" type="submit">Create template</button>
      </form>
    </div>

    <div class="card">
      <h2>Templates (${templates.length})</h2>
      ${templates.length === 0 ? '<div class="empty">No templates yet.</div>' : `
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Name</th><th>Slug</th><th>Description</th><th>Created by</th><th></th></tr></thead>
        <tbody>
          ${templates.map((t) => `
            <tr>
              <td><strong>${escapeHtml(t.name)}</strong></td>
              <td><code>${escapeHtml(t.slug)}</code></td>
              <td>${escapeHtml(t.description || '')}</td>
              <td>${escapeHtml(t.created_by_username || '')}</td>
              <td>
                <button class="btn btn-sm btn-secondary" data-edit-tpl="${t.id}" data-name="${escapeHtml(t.name)}" data-desc="${escapeHtml(t.description || '')}" data-body="${escapeHtml(t.body || '')}">Edit</button>
                ${isAdmin() ? `<button class="btn btn-sm btn-danger" data-delete-tpl="${t.id}">Delete</button>` : ''}
              </td>
            </tr>`).join('')}
        </tbody>
      </table></div>`}
    </div>

    <div class="card">
      <h2>Navigation menus</h2>
      <form id="menu-form" style="display:flex; gap:10px; flex-wrap:wrap; max-width:640px; margin-bottom:14px;">
        <input id="menu-label" placeholder="Label" maxlength="80" style="flex:1; padding:8px; border:1px solid var(--border); border-radius:6px;">
        <input id="menu-url" placeholder="/path" maxlength="200" style="flex:2; padding:8px; border:1px solid var(--border); border-radius:6px;">
        <button class="btn btn-sm" type="submit">Add menu item</button>
      </form>
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Position</th><th>Label</th><th>URL</th><th></th></tr></thead>
        <tbody>
          ${menus.map((m) => `
            <tr>
              <td>${m.position}</td>
              <td>${escapeHtml(m.label)}</td>
              <td><code>${escapeHtml(m.url)}</code></td>
              <td>
                <button class="btn btn-sm btn-secondary" data-edit-menu="${m.id}" data-label="${escapeHtml(m.label)}" data-url="${escapeHtml(m.url)}" data-pos="${m.position}">Edit</button>
                ${isAdmin() ? `<button class="btn btn-sm btn-danger" data-delete-menu="${m.id}">Delete</button>` : ''}
              </td>
            </tr>`).join('')}
        </tbody>
      </table></div>
    </div>
  `;

  document.getElementById('tpl-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      await api('/api/cms/page_templates', { method: 'POST', body: {
        name: document.getElementById('tpl-name').value.trim(),
        slug: document.getElementById('tpl-slug').value.trim() || undefined,
        description: document.getElementById('tpl-desc').value.trim() || null,
        body: document.getElementById('tpl-body').value
      } });
      toast('Template created.', 'success');
      viewTemplates();
    } catch (err) { toast(err.message, 'error'); }
  });

  viewEl.querySelectorAll('[data-edit-tpl]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const name = window.prompt('Name:', btn.dataset.name);
      if (!name) return;
      const desc = window.prompt('Description:', btn.dataset.desc);
      const body = window.prompt('Template body:', btn.dataset.body);
      try {
        await api(`/api/cms/page_templates/${btn.dataset.editTpl}`, { method: 'PATCH', body: { name: name.trim(), description: desc || null, body: body || '' } });
        toast('Template updated.', 'success');
        viewTemplates();
      } catch (err) { toast(err.message, 'error'); }
    });
  });

  viewEl.querySelectorAll('[data-delete-tpl]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!window.confirm('Delete this template?')) return;
      try {
        await api(`/api/cms/page_templates/${btn.dataset.deleteTpl}`, { method: 'DELETE' });
        toast('Template deleted.', 'success');
        viewTemplates();
      } catch (err) { toast(err.message, 'error'); }
    });
  });

  document.getElementById('menu-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      await api('/api/cms/page_templates/menus', { method: 'POST', body: {
        label: document.getElementById('menu-label').value.trim(),
        url: document.getElementById('menu-url').value.trim()
      } });
      toast('Menu item added.', 'success');
      viewTemplates();
    } catch (err) { toast(err.message, 'error'); }
  });

  viewEl.querySelectorAll('[data-edit-menu]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const label = window.prompt('Label:', btn.dataset.label);
      if (!label) return;
      const url = window.prompt('URL:', btn.dataset.url);
      if (!url) return;
      try {
        await api(`/api/cms/page_templates/menus/${btn.dataset.editMenu}`, { method: 'PATCH', body: { label: label.trim(), url: url.trim() } });
        toast('Menu item updated.', 'success');
        viewTemplates();
      } catch (err) { toast(err.message, 'error'); }
    });
  });

  viewEl.querySelectorAll('[data-delete-menu]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        await api(`/api/cms/page_templates/menus/${btn.dataset.deleteMenu}`, { method: 'DELETE' });
        toast('Menu item deleted.', 'success');
        viewTemplates();
      } catch (err) { toast(err.message, 'error'); }
    });
  });
}

// ------------------------------------------------------------- Users & roles (CMS-09)
async function viewUsers() {
  if (!isAdmin()) return guard(['admin']);
  setTitle('Users & roles');
  setActions('');

  const res = await api('/api/cms/user_and_role_management');
  const { users, roles, history } = res.data;

  viewEl.innerHTML = `
    <div class="card">
      <h2>Create user</h2>
      <form id="user-form" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px;">
        <div class="field"><label>Username</label><input id="u-username" maxlength="32" required></div>
        <div class="field"><label>Email</label><input id="u-email" type="email" maxlength="120" required></div>
        <div class="field"><label>Password</label><input id="u-password" type="password" maxlength="128" required></div>
        <div class="field">
          <label>Role</label>
          <select id="u-role">${roles.map((r) => `<option value="${r.id}">${escapeHtml(r.name)}</option>`).join('')}</select>
        </div>
        <div class="field"><label>Display name</label><input id="u-display" maxlength="80"></div>
        <div class="field" style="align-self:end;"><button class="btn" type="submit">Create user</button></div>
      </form>
    </div>

    <div class="card">
      <h2>Users (${users.length})</h2>
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
          ${users.map((u) => `
            <tr>
              <td><strong>${escapeHtml(u.username)}</strong><div style="color:var(--muted); font-size:0.82rem;">${escapeHtml(u.display_name || '')}</div></td>
              <td>${escapeHtml(u.email)}</td>
              <td>
                <select data-role-select="${u.id}" ${u.id === me.id ? 'disabled title="You cannot change your own role"' : ''}>
                  ${roles.map((r) => `<option value="${r.id}" ${u.role_id === r.id ? 'selected' : ''}>${escapeHtml(r.name)}</option>`).join('')}
                </select>
              </td>
              <td>${statusPill(u.status)}</td>
              <td>${shortDate(u.created_at)}</td>
              <td>
                ${u.id !== me.id ? `
                  <button class="btn btn-sm ${u.status === 'active' ? 'btn-warning' : 'btn-success'}" data-toggle-status="${u.id}" data-status="${u.status}">${u.status === 'active' ? 'Disable' : 'Enable'}</button>` : '<span style="color:var(--muted)">you</span>'}
              </td>
            </tr>`).join('')}
        </tbody>
      </table></div>
    </div>

    <div class="card">
      <h2>Role assignment history (auditable)</h2>
      ${history.length === 0 ? '<div class="empty">No role changes yet.</div>' : `
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>User</th><th>Action</th><th>From role</th><th>To role</th><th>Actor</th><th>Note</th><th>When</th></tr></thead>
        <tbody>
          ${history.map((h) => `
            <tr>
              <td>${escapeHtml(h.target_username)}</td>
              <td>${escapeHtml(h.action)}</td>
              <td>${escapeHtml(h.previous_role || '—')}</td>
              <td>${escapeHtml(h.new_role || '—')}</td>
              <td>${escapeHtml(h.actor_username || '—')}</td>
              <td>${escapeHtml(h.note || '')}</td>
              <td>${formatDate(h.created_at)}</td>
            </tr>`).join('')}
        </tbody>
      </table></div>`}
    </div>
  `;

  document.getElementById('user-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      await api('/api/cms/user_and_role_management', { method: 'POST', body: {
        username: document.getElementById('u-username').value.trim(),
        email: document.getElementById('u-email').value.trim(),
        password: document.getElementById('u-password').value,
        roleId: Number(document.getElementById('u-role').value),
        displayName: document.getElementById('u-display').value.trim() || null
      } });
      toast('User created.', 'success');
      viewUsers();
    } catch (err) { toast(err.message, 'error'); }
  });

  viewEl.querySelectorAll('[data-role-select]').forEach((sel) => {
    sel.addEventListener('change', async () => {
      try {
        await api(`/api/cms/user_and_role_management/${sel.dataset.roleSelect}`, { method: 'PATCH', body: { roleId: Number(sel.value) } });
        toast('Role updated and audited.', 'success');
        viewUsers();
      } catch (err) { toast(err.message, 'error'); viewUsers(); }
    });
  });

  viewEl.querySelectorAll('[data-toggle-status]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const next = btn.dataset.status === 'active' ? 'disabled' : 'active';
      try {
        await api(`/api/cms/user_and_role_management/${btn.dataset.toggleStatus}`, { method: 'PATCH', body: { status: next } });
        toast(`User ${next === 'active' ? 'enabled' : 'disabled'}.`, 'success');
        viewUsers();
      } catch (err) { toast(err.message, 'error'); }
    });
  });
}

// ------------------------------------------------------------- Plugin settings (CMS-10)
async function viewSettings() {
  if (!isAdmin()) return guard(['admin']);
  setTitle('Plugin / settings panel');
  setActions('');

  const res = await api('/api/cms/plugin_settings_panel');
  const settings = res.data || [];

  viewEl.innerHTML = `
    <div class="card">
      <h2>Add setting</h2>
      <form id="setting-form" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px;">
        <div class="field"><label>Key</label><input id="s-key" placeholder="site_setting_name" maxlength="80" required></div>
        <div class="field"><label>Value</label><input id="s-value" maxlength="2000" required></div>
        <div class="field"><label>Description</label><input id="s-desc" maxlength="300"></div>
        <div class="field" style="align-self:end;"><button class="btn" type="submit">Add setting</button></div>
      </form>
    </div>

    <div class="card">
      <h2>Settings (${settings.length})</h2>
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Key</th><th>Value</th><th>Description</th><th>Updated by</th><th></th></tr></thead>
        <tbody>
          ${settings.map((s) => `
            <tr>
              <td><code>${escapeHtml(s.key)}</code></td>
              <td>${escapeHtml(s.value)}</td>
              <td style="max-width:260px;">${escapeHtml(s.description || '')}</td>
              <td>${escapeHtml(s.updated_by_username || '—')}</td>
              <td>
                <button class="btn btn-sm btn-secondary" data-edit-setting="${s.id}" data-value="${escapeHtml(s.value)}" data-key="${escapeHtml(s.key)}">Edit</button>
                ${['site_name', 'allow_comments', 'moderate_comments'].includes(s.key) ? '' : `<button class="btn btn-sm btn-danger" data-delete-setting="${s.id}">Delete</button>`}
              </td>
            </tr>`).join('')}
        </tbody>
      </table></div>
    </div>
  `;

  document.getElementById('setting-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      await api('/api/cms/plugin_settings_panel', { method: 'POST', body: {
        key: document.getElementById('s-key').value.trim(),
        value: document.getElementById('s-value').value,
        description: document.getElementById('s-desc').value.trim() || null
      } });
      toast('Setting added.', 'success');
      viewSettings();
    } catch (err) { toast(err.message, 'error'); }
  });

  viewEl.querySelectorAll('[data-edit-setting]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const value = window.prompt(`Value for "${btn.dataset.key}":`, btn.dataset.value);
      if (value === null) return;
      try {
        await api(`/api/cms/plugin_settings_panel/${btn.dataset.editSetting}`, { method: 'PATCH', body: { value } });
        toast('Setting updated.', 'success');
        viewSettings();
      } catch (err) { toast(err.message, 'error'); }
    });
  });

  viewEl.querySelectorAll('[data-delete-setting]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!window.confirm('Delete this setting?')) return;
      try {
        await api(`/api/cms/plugin_settings_panel/${btn.dataset.deleteSetting}`, { method: 'DELETE' });
        toast('Setting deleted.', 'success');
        viewSettings();
      } catch (err) { toast(err.message, 'error'); }
    });
  });
}

// ------------------------------------------------------------- Import / export (CMS-11)
async function viewImportExport() {
  if (!isAdmin()) return guard(['admin']);
  setTitle('Import / export');
  setActions('');

  const res = await api('/api/cms/import_export?pageSize=100');
  const records = res.data || [];

  viewEl.innerHTML = `
    <div class="stat-grid">
      <div class="card" style="margin:0;">
        <h2>Export site data</h2>
        <p style="color:var(--muted); margin-top:0;">Exports articles, categories, templates, menus, comments and settings.</p>
        <div class="actions" style="margin:0;">
          <button class="btn" data-export="json">Export JSON</button>
          <button class="btn btn-secondary" data-export="csv">Export CSV (articles)</button>
        </div>
      </div>
      <div class="card" style="margin:0;">
        <h2>Import content</h2>
        <p style="color:var(--muted); margin-top:0;">Upload a JSON export file to import content.</p>
        <form id="import-form">
          <div class="field"><input id="import-file" type="file" accept=".json,application/json" required></div>
          <button class="btn" type="submit">Import file</button>
        </form>
      </div>
    </div>

    <div class="card">
      <h2>History</h2>
      ${records.length === 0 ? '<div class="empty">No import/export jobs yet.</div>' : `
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Kind</th><th>Format</th><th>Status</th><th>File</th><th>Records</th><th>Requested by</th><th>When</th><th></th></tr></thead>
        <tbody>
          ${records.map((r) => `
            <tr>
              <td>${statusPill(r.kind)}</td>
              <td><code>${escapeHtml(r.format)}</code></td>
              <td>${statusPill(r.status)}</td>
              <td>${escapeHtml(r.file_name || '—')} ${r.file_size ? `<span style="color:var(--muted)">(${Math.round(r.file_size / 1024)} KB)</span>` : ''}</td>
              <td>${r.record_count}</td>
              <td>${escapeHtml(r.requested_by_username || '')}</td>
              <td>${formatDate(r.created_at)}</td>
              <td>${r.file_id ? `<a class="btn btn-sm btn-secondary" href="/api/cms/import_export/${r.id}/download">Download</a>` : ''}</td>
            </tr>`).join('')}
        </tbody>
      </table></div>`}
    </div>
  `;

  viewEl.querySelectorAll('[data-export]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        const result = await api('/api/cms/import_export', { method: 'POST', body: { format: btn.dataset.export, scope: 'full' } });
        toast(`Export created: ${result.data.file_name}.`, 'success');
        viewImportExport();
      } catch (err) { toast(err.message, 'error'); }
    });
  });

  document.getElementById('import-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const file = document.getElementById('import-file').files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    try {
      const result = await api('/api/cms/import_export', { method: 'POST', body: fd });
      toast(`Import complete: ${result.data.summary.articlesCreated} articles imported.`, 'success');
      viewImportExport();
    } catch (err) { toast(err.message, 'error'); }
  });
}

// ------------------------------------------------------------- API diagnostics (CMS-12)
async function viewDiagnostics() {
  setTitle('Frontend API integration & errors');
  setActions('');

  const logRes = await api('/api/cms/frontend_api_integration_and_errors?pageSize=100');
  const log = (logRes && logRes.data) || [];

  viewEl.innerHTML = `
    <div class="card">
      <h2>Trigger controlled API response states</h2>
      <p style="color:var(--muted); margin-top:0;">Each button calls a diagnostics endpoint and records the outcome in the event log below.</p>
      <div class="actions" style="margin:0;">
        <button class="btn btn-sm" data-diag="validation">Validation (400)</button>
        <button class="btn btn-sm btn-warning" data-diag="not-found">Missing page (404)</button>
        <button class="btn btn-sm btn-danger" data-diag="forbidden">Permission (403)</button>
        <button class="btn btn-sm btn-danger" data-diag="server-error">Server error (500)</button>
        <button class="btn btn-sm btn-success" data-diag="preview">Preview (200)</button>
      </div>
      <div id="diag-result" class="empty" style="margin-top:12px; text-align:left;">Run a check to see the controlled response.</div>
    </div>

    <div class="card">
      <h2>Recorded frontend API events (${log.length})</h2>
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Kind</th><th>Status</th><th>Context</th><th>Message</th><th>URL</th><th>Resolved</th><th>When</th></tr></thead>
        <tbody>
          ${log.length === 0 ? '<tr><td colspan="7"><div class="empty">No events recorded.</div></td></tr>' : log.map((e) => `
            <tr>
              <td><span class="pill">${escapeHtml(e.kind)}</span></td>
              <td>${e.status_code || '—'}</td>
              <td>${escapeHtml(e.context || '—')}</td>
              <td style="max-width:260px;">${escapeHtml(e.message)}</td>
              <td style="max-width:200px;">${escapeHtml(e.url || '')}</td>
              <td>
                <button class="btn btn-sm ${e.resolved ? 'btn-success' : 'btn-secondary'}" data-toggle-resolve="${e.id}" data-resolved="${e.resolved}">${e.resolved ? 'Resolved' : 'Open'}</button>
              </td>
              <td>${formatDate(e.created_at)}</td>
            </tr>`).join('')}
        </tbody>
      </table></div>
    </div>
  `;

  const resultBox = document.getElementById('diag-result');

  viewEl.querySelectorAll('[data-diag]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const kind = btn.dataset.diag;
      const url = `/api/diagnostics/${kind}`;
      resultBox.innerHTML = `<div class="empty" style="text-align:left;">Calling <code>${url}</code>…</div>`;
      let status = 0;
      let message = '';
      try {
        const res = await fetch(url);
        status = res.status;
        const json = await res.json().catch(() => null);
        message = (json && json.error && json.error.message) || (json && json.message) || 'OK';
      } catch (err) {
        status = 0;
        message = err.message;
      }
      resultBox.innerHTML = `
        <div><strong>${escapeHtml(kind)}</strong> → HTTP ${status}</div>
        <div style="color:var(--muted);">${escapeHtml(message)}</div>`;
      try {
        await api('/api/cms/frontend_api_integration_and_errors', {
          method: 'POST',
          body: { kind, statusCode: status, context: 'diagnostics panel', message, url }
        });
      } catch { /* ignore */ }
      setTimeout(() => viewDiagnostics(), 400);
    });
  });

  viewEl.querySelectorAll('[data-toggle-resolve]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        await api(`/api/cms/frontend_api_integration_and_errors/${btn.dataset.toggleResolve}`, {
          method: 'PATCH',
          body: { resolved: Number(btn.dataset.resolved) === 1 ? 0 : 1 }
        });
        viewDiagnostics();
      } catch (err) { toast(err.message, 'error'); }
    });
  });
}

boot();
