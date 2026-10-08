/* P14 Workflow Automation / Agentic Task Platform — client behaviour */

function esc(s) {
  if (s === null || s === undefined) return '';
  return String(s).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

function toast(msg, ok) {
  var t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.className = 'toast ' + (ok === false ? 'err' : 'ok');
  clearTimeout(t._h);
  t._h = setTimeout(function () { t.classList.add('hidden'); }, 3500);
}

async function api(path, opts) {
  opts = opts || {};
  var headers = {};
  if (opts.body && !(opts.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(opts.body);
  } else if (opts.body) {
    headers = {}; // let the browser set multipart boundary
  }
  var res = await fetch(path, { method: opts.method || 'GET', headers: headers, body: opts.body || undefined });
  if (res.status === 401) {
    window.location.href = '/login';
    throw new Error('not signed in');
  }
  var data = {};
  try { data = await res.json(); } catch (e) { /* non-JSON */ }
  if (!res.ok) throw new Error(data.error || ('request failed: ' + res.status));
  return data;
}

/* ---------------- auth pages ---------------- */
(function () {
  var form = document.getElementById('auth-form');
  if (!form) return;
  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    var body = Object.fromEntries(new FormData(form).entries());
    try {
      await api('/' + form.dataset.mode, { method: 'POST', body: body });
      window.location.href = '/';
    } catch (err) {
      toast(err.message, false);
    }
  });
})();

/* ---------------- generic resource page ---------------- */
(function () {
  var PAGE = window.PAGE;
  if (!PAGE || !PAGE.resource) return;

  var rowsEl = document.getElementById('rows');
  var API = '/api/agent/' + PAGE.resource;

  function renderCell(col, value) {
    if (value === null || value === undefined || value === '') return '<span class="muted">—</span>';
    if (col.Render === 'bool') {
      return value ? '<span class="badge yes">yes</span>' : '<span class="badge no">no</span>';
    }
    if (col.Render === 'link') {
      return '<a href="' + col.Href.replace('{value}', encodeURIComponent(value)) + '">#' + esc(value) + '</a>';
    }
    var s = String(value);
    if (s.length > 60) s = s.slice(0, 60) + '…';
    return esc(s);
  }

  function rowHtml(row) {
    var cells = (PAGE.columns || []).map(function (c) {
      return '<td>' + renderCell(c, row[c.Key]) + '</td>';
    }).join('');
    var acts = (PAGE.actions || []).map(function (a) {
      var path = a.Path.replace('{id}', row.id);
      return '<button class="btn tiny" data-action="' + a.Key + '" data-id="' + row.id + '" data-path="' + path +
        '" data-method="' + a.Method + '" data-confirm="' + esc(a.Confirm || '') + '">' + esc(a.Label) + '</button>';
    }).join('');
    return '<tr>' + cells + '<td class="actions-col">' + acts + '</td></tr>';
  }

  async function load() {
    var qs = new URLSearchParams(window.location.search);
    rowsEl.innerHTML = '<tr><td colspan="99" class="muted">Loading…</td></tr>';
    try {
      var data = await api(API + '?' + qs.toString());
      var rows = data.items || [];
      if (!rows.length) {
        rowsEl.innerHTML = '<tr><td colspan="99" class="muted">No records.</td></tr>';
        return;
      }
      rowsEl.innerHTML = rows.map(rowHtml).join('');
    } catch (err) {
      rowsEl.innerHTML = '<tr><td colspan="99" class="muted">' + esc(err.message) + '</td></tr>';
    }
  }

  var filterForm = document.getElementById('filter-form');
  if (filterForm) {
    filterForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var cur = new URLSearchParams(window.location.search);
      cur.delete('limit'); cur.delete('offset');
      new FormData(filterForm).forEach(function (v, k) {
        if (v) cur.set(k, v); else cur.delete(k);
      });
      window.location.search = cur.toString();
    });
  }
  var clearBtn = document.getElementById('clear-filters');
  if (clearBtn) clearBtn.addEventListener('click', function () { window.location.search = ''; });

  var newBtn = document.getElementById('new-btn');
  var newForm = document.getElementById('new-form');
  var cancelNew = document.getElementById('cancel-new');
  if (newBtn && newForm) {
    newBtn.addEventListener('click', function () { newForm.classList.toggle('hidden'); });
    if (cancelNew) cancelNew.addEventListener('click', function () { newForm.classList.add('hidden'); });
  }

  var createForm = document.getElementById('create-form');
  if (createForm) {
    createForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      var body = Object.fromEntries(new FormData(createForm).entries());
      (PAGE.fields || []).forEach(function (f) {
        if (f.Type === 'number' && body[f.Key] !== '') body[f.Key] = Number(body[f.Key]);
        if (f.Type === 'checkbox') body[f.Key] = body[f.Key] === 'true';
      });
      try {
        var data = await api(API, { method: 'POST', body: body });
        toast('Created record #' + (data.item && data.item.id));
        createForm.reset();
        newForm.classList.add('hidden');
        load();
      } catch (err) { toast(err.message, false); }
    });
  }

  var uploadForm = document.getElementById('upload-form');
  if (uploadForm) {
    uploadForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      try {
        var data = await api(API, { method: 'POST', body: new FormData(uploadForm) });
        toast('Uploaded #' + (data.item && data.item.id));
        uploadForm.reset();
        load();
      } catch (err) { toast(err.message, false); }
    });
  }

  var replayForm = document.getElementById('replay-form');
  if (replayForm) {
    replayForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      var runId = new FormData(replayForm).get('run_id');
      try {
        var data = await api(API, { method: 'POST', body: { action: 'replay', run_id: Number(runId) } });
        toast('Replay started as run #' + (data.item && data.item.id));
        window.location.href = '/runs/' + (data.item && data.item.id);
      } catch (err) { toast(err.message, false); }
    });
  }

  rowsEl.addEventListener('click', async function (e) {
    var btn = e.target.closest('button[data-action]');
    if (!btn) return;
    var path = btn.dataset.path;
    if (btn.dataset.confirm && !window.confirm(btn.dataset.confirm)) return;
    if (btn.dataset.method === 'GET') { window.location.href = path; return; }
    var def = (PAGE.actions || []).filter(function (a) { return a.Key === btn.dataset.action; })[0];
    var body = (def && def.Body) || {};
    try {
      var data = await api(path, { method: btn.dataset.method, body: body });
      if (btn.dataset.action === 'replay') {
        window.location.href = '/runs/' + (data.item && data.item.id);
        return;
      }
      toast('Action completed');
      load();
    } catch (err) { toast(err.message, false); }
  });

  load();
})();

/* ---------------- run detail page ---------------- */
(function () {
  if (window.RUN_ID === undefined) return;
  var runId = window.RUN_ID;
  var logEl = document.getElementById('log-stream');
  var statusEl = document.getElementById('run-status');
  var metaEl = document.getElementById('run-meta');

  api('/api/agent/task_execution/' + runId).then(function (data) {
    var r = data.item;
    statusEl.textContent = r.status;
    statusEl.className = 'badge st-' + r.status;
    metaEl.textContent = 'workflow ' + r.workflow_id + ' · trigger ' + r.trigger + ' · started ' + r.started_at +
      (r.finished_at ? ' · finished ' + r.finished_at : '');
  }).catch(function (err) { metaEl.textContent = err.message; });

  var proto = location.protocol === 'https:' ? 'wss' : 'ws';
  var ws = new WebSocket(proto + '://' + location.host + '/api/agent/ws/runs/' + runId);
  ws.onmessage = function (ev) {
    if (ev.data === '__end__') { logEl.textContent += '\n— stream ended —'; return; }
    logEl.textContent += ev.data + '\n';
    logEl.scrollTop = logEl.scrollHeight;
  };
  ws.onclose = function () {
    if (logEl.textContent.indexOf('— stream ended —') === -1) logEl.textContent += '\n— connection closed —';
  };
  ws.onerror = function () { logEl.textContent += '\n— connection error —'; };

  var replayBtn = document.getElementById('replay-run');
  if (replayBtn) {
    replayBtn.addEventListener('click', async function () {
      try {
        var data = await api('/api/agent/task_execution/' + runId + '/replay', { method: 'POST', body: {} });
        toast('Replay started as run #' + (data.item && data.item.id));
        window.location.href = '/runs/' + (data.item && data.item.id);
      } catch (err) { toast(err.message, false); }
    });
  }
})();

/* ---------------- admin governance page ---------------- */
(function () {
  if (!document.getElementById('settings-rows')) return;
  var settingsEl = document.getElementById('settings-rows');
  var usersEl = document.getElementById('users-rows');
  var auditEl = document.getElementById('audit-rows');
  var GOV = '/api/agent/admin_governance';

  async function loadSettings() {
    try {
      var data = await api(GOV);
      settingsEl.innerHTML = (data.items || []).map(function (s) {
        return '<tr><td><code>' + esc(s.setting_key) + '</code></td><td>' + esc(s.setting_value) + '</td><td>' + s.updated_by +
          '</td><td>' + esc(s.updated_at) + '</td>' +
          '<td><button class="btn tiny" data-edit-setting data-id="' + s.id + '" data-value="' + esc(s.setting_value) + '">Edit</button></td></tr>';
      }).join('');
    } catch (err) { settingsEl.innerHTML = '<tr><td colspan="5" class="muted">' + esc(err.message) + '</td></tr>'; }
  }

  async function loadUsers(q) {
    var url = q ? GOV + '/users?q=' + encodeURIComponent(q) : GOV + '/users';
    try {
      var data = await api(url);
      usersEl.innerHTML = (data.items || []).map(function (u) {
        return '<tr><td>' + u.id + '</td><td>' + esc(u.username) + '</td><td>' + esc(u.email) + '</td><td>' + esc(u.role) + '</td><td>' +
          (u.active ? '<span class="badge yes">active</span>' : '<span class="badge no">disabled</span>') + '</td><td>' +
          '<button class="btn tiny" data-user-toggle data-id="' + u.id + '" data-active="' + u.active + '">' + (u.active ? 'Disable' : 'Enable') + '</button> ' +
          '<button class="btn tiny" data-user-role data-id="' + u.id + '" data-role="' + u.role + '">Make ' + (u.role === 'admin' ? 'user' : 'admin') + '</button>' +
          '</td></tr>';
      }).join('');
    } catch (err) { usersEl.innerHTML = '<tr><td colspan="6" class="muted">' + esc(err.message) + '</td></tr>'; }
  }

  async function loadAudit() {
    try {
      var data = await api(GOV + '/audit');
      auditEl.innerHTML = (data.items || []).map(function (a) {
        return '<tr><td>' + a.id + '</td><td>' + a.actor_id + '</td><td>' + esc(a.action) + '</td><td>' + esc(a.entity_type) +
          '</td><td>' + esc(a.detail) + '</td><td>' + esc(a.created_at) + '</td></tr>';
      }).join('');
    } catch (err) { auditEl.innerHTML = '<tr><td colspan="6" class="muted">' + esc(err.message) + '</td></tr>'; }
  }

  loadSettings();
  loadUsers('');
  loadAudit();

  var settingForm = document.getElementById('setting-new-form');
  var settingBtn = document.getElementById('setting-new-btn');
  if (settingForm && settingBtn) {
    settingBtn.addEventListener('click', function () { settingForm.classList.toggle('hidden'); });
    settingForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      var body = Object.fromEntries(new FormData(settingForm).entries());
      try {
        await api(GOV, { method: 'POST', body: body });
        toast('Setting saved');
        settingForm.reset();
        settingForm.classList.add('hidden');
        loadSettings();
      } catch (err) { toast(err.message, false); }
    });
  }

  settingsEl.addEventListener('click', async function (e) {
    var btn = e.target.closest('button[data-edit-setting]');
    if (!btn) return;
    var next = window.prompt('New value for setting', btn.dataset.value);
    if (next === null) return;
    try {
      await api(GOV + '/' + btn.dataset.id, { method: 'PATCH', body: { setting_value: next } });
      toast('Setting updated');
      loadSettings();
    } catch (err) { toast(err.message, false); }
  });

  var runScheduler = document.getElementById('run-scheduler-btn');
  if (runScheduler) {
    runScheduler.addEventListener('click', async function () {
      try {
        var data = await api(GOV + '/run-scheduler', { method: 'POST', body: {} });
        toast('Scheduler ran ' + data.ran + ' due schedule(s)');
      } catch (err) { toast(err.message, false); }
    });
  }

  usersEl.addEventListener('click', async function (e) {
    var t = e.target.closest('button[data-user-toggle]');
    var r = e.target.closest('button[data-user-role]');
    try {
      if (t) {
        await api(GOV + '/users/' + t.dataset.id, { method: 'POST', body: { active: t.dataset.active !== 'true' } });
        toast('User updated');
        loadUsers(new URLSearchParams(window.location.search).get('q') || '');
      } else if (r) {
        await api(GOV + '/users/' + r.dataset.id, { method: 'POST', body: { role: r.dataset.role === 'admin' ? 'user' : 'admin' } });
        toast('Role updated');
        loadUsers(new URLSearchParams(window.location.search).get('q') || '');
      }
    } catch (err) { toast(err.message, false); }
  });

  var usersFilter = document.getElementById('users-filter-form');
  if (usersFilter) {
    usersFilter.addEventListener('submit', function (e) {
      e.preventDefault();
      loadUsers(new FormData(usersFilter).get('q') || '');
    });
  }
})();
