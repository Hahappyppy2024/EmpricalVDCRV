(function () {
  const state = { user: null, lookups: null };
  const main = document.getElementById('main');

  const esc = (s) => String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const money = (n) => {
    const v = Number(n || 0);
    return v.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
  };
  const dt = (s) => (s ? String(s).replace('T', ' ').slice(0, 16) : '-');

  const badge = (s) => `<span class="badge ${esc(s)}">${esc(String(s).replace(/_/g, ' '))}</span>`;

  function toast(msg, isError) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.style.background = isError ? '#c0392b' : '#26313d';
    el.classList.remove('hidden');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.add('hidden'), 3500);
  }

  function boot() {
    API.get('/api/auth/me')
      .then((user) => {
        state.user = user;
        return API.get('/api/meta/lookups');
      })
      .then((lookups) => {
        state.lookups = lookups;
        renderChrome();
        setupRealtime();
        window.addEventListener('hashchange', route);
        route();
      })
      .catch(() => {
        window.location.href = '/login.html';
      });
  }

  function renderChrome() {
    const role = state.user.role;
    const items = [
      { h: 'dashboard', l: 'Dashboard', roles: ['*'] },
      { h: 'reports', l: 'Expense Reports', roles: ['employee', 'manager', 'finance', 'admin'] },
      { h: 'approvals', l: 'Manager Approvals', roles: ['manager', 'admin'] },
      { h: 'finance', l: 'Finance Review', roles: ['finance', 'admin'] },
      { h: 'policies', l: 'Policy Rules', roles: ['employee', 'manager', 'finance', 'admin'] },
      { h: 'exports', l: 'Reimbursement Export', roles: ['finance', 'admin'] },
      { h: 'admin', l: 'Admin Config', roles: ['admin'] },
      { h: 'account', l: 'Account', roles: ['*'] },
    ];
    const nav = document.getElementById('nav');
    nav.innerHTML = items
      .filter((i) => i.roles.includes('*') || i.roles.includes(role))
      .map((i) => `<a href="#${i.h}" data-view="${i.h}">${i.l}</a>`)
      .join('');
    nav.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => setActiveNav(a.dataset.view)));
    document.getElementById('user-chip').innerHTML =
      `<span>${esc(state.user.full_name)} (${badge(state.user.role)})</span>` +
      `<button class="secondary small" id="signout">Sign out</button>`;
    document.getElementById('signout').addEventListener('click', async () => {
      await API.post('/api/auth/logout');
      window.location.href = '/login.html';
    });
  }

  function setActiveNav(view) {
    document.querySelectorAll('#nav a').forEach((a) => a.classList.toggle('active', a.dataset.view === view));
  }

  function setupRealtime() {
    const statusEl = document.getElementById('rt-status');
    let socket;
    function connect() {
      const proto = location.protocol === 'https:' ? 'wss' : 'ws';
      socket = new WebSocket(`${proto}://${location.host}/ws`);
      socket.onopen = () => { statusEl.textContent = 'realtime: online'; statusEl.classList.add('online'); };
      socket.onmessage = (evt) => {
        try {
          const msg = JSON.parse(evt.data);
          if (msg.type === 'comment.created') {
            toast(`New comment on ${msg.data.report_no} by ${msg.data.author_name}`);
          } else if (msg.type === 'approval.decision') {
            toast(`Approval decision by ${msg.data.actor}: ${msg.data.decision}`);
          }
        } catch (_) { /* ignore */ }
      };
      socket.onclose = () => {
        statusEl.textContent = 'realtime: reconnecting';
        statusEl.classList.remove('online');
        setTimeout(connect, 2000);
      };
      socket.onerror = () => socket.close();
    }
    connect();
  }

  function route() {
    const hash = location.hash.slice(1) || 'dashboard';
    const [view, param] = hash.split('/');
    setActiveNav(view);
    const renderers = {
      dashboard: renderDashboard,
      reports: renderReports,
      approvals: renderApprovals,
      finance: renderFinance,
      policies: renderPolicies,
      exports: renderExports,
      admin: renderAdmin,
      account: renderAccount,
    };
    const fn = renderers[view] || renderDashboard;
    fn(param);
  }

  async function renderDashboard() {
    const data = await API.get('/api/exp/account_access');
    const d = data.dashboard;
    const stats = [];
    if (d.role === 'employee') {
      stats.push(['My reports', d.counts.draft + d.counts.submitted + d.counts.approved + d.counts.finance_approved + d.counts.reimbursed + d.counts.rejected + d.counts.changes_requested + d.counts.finance_rejected], ['Drafts', d.counts.draft], ['Pending', d.counts.submitted], ['Total amount', money(d.per_role.employee.my_total_amount)]);
    }
    if (d.role === 'manager') {
      stats.push(['Pending approvals', d.per_role.manager.pending_approvals], ['Direct reports', d.per_role.manager.direct_reports], ['Submitted in dept', d.counts.submitted]);
    }
    if (d.role === 'finance') {
      stats.push(['Pending reviews', d.per_role.finance.pending_reviews], ['Enabled policy rules', d.per_role.finance.enabled_policy_rules], ['Approved awaiting finance', d.counts.approved], ['Finance approved', d.counts.finance_approved]);
    }
    if (d.role === 'admin') {
      stats.push(['Enabled policy rules', d.per_role.admin.enabled_policy_rules], ['Departments', d.per_role.admin.departments], ['Total reports', Object.values(d.counts).reduce((a, b) => a + b, 0)]);
    }
    const warnings = data.dashboard && data.dashboard.counts ? [] : [];
    const warningHtml = warnings.length ? `<div class="alert warning">${warnings.join('<br/>')}</div>` : '';
    const cfg = await API.get('/api/exp/frontend_api_integration_and_errors');
    const draftWarnings = (cfg.draft_warnings || []).flatMap((w) => w.warnings.map((x) => ({ report_no: w.report_no, ...x })));
    main.innerHTML = `
      <h1>Dashboard</h1>
      <div class="grid">
        ${stats.map(([l, n]) => `<div class="stat"><div class="num">${esc(typeof n === 'string' ? n : Number(n).toLocaleString())}</div><div class="lbl">${esc(l)}</div></div>`).join('')}
      </div>
      <div class="panel" style="margin-top:16px">
        <h2>Policy warnings on your drafts</h2>
        ${draftWarnings.length ? draftWarnings.map((w) => `<div class="warning-item">${esc(w.report_no)} - ${esc(w.message)}</div>`).join('') : `<p class="muted">No policy warnings on your draft reports.</p>`}
      </div>
      ${warningHtml}
    `;
  }

  async function renderReports(param) {
    if (param === 'new') return renderReportForm();
    if (param && param.startsWith('detail/')) return renderReportDetail(param.split('/')[1]);
    const data = await API.get('/api/exp/expense_report_creation');
    const canCreate = ['employee', 'admin'].includes(state.user.role);
    main.innerHTML = `
      <div class="panel">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <h2 style="margin:0">Expense reports</h2>
          ${canCreate ? `<a class="button" href="#reports/new" style="background:var(--primary);color:#fff;border-radius:8px;padding:8px 12px;text-decoration:none">+ New report</a>` : ''}
        </div>
        <table>
          <thead><tr><th>Report</th><th>Title</th><th>Employee</th><th>Total</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
          <tbody>
            ${data.reports.map((r) => `
              <tr>
                <td class="mono">${esc(r.report_no)}</td>
                <td>${esc(r.title)}</td>
                <td>${esc(r.employee_name)}</td>
                <td>${money(r.total_amount)}</td>
                <td>${badge(r.status)}</td>
                <td class="muted">${dt(r.submitted_at)}</td>
                <td><a href="#reports/detail/${r.id}" class="button small secondary">Open</a></td>
              </tr>`).join('')}
          </tbody>
        </table>
        ${data.reports.length ? '' : '<p class="muted">No reports to show.</p>'}
      </div>
    `;
  }

  function categoryOptions(selected) {
    return (state.lookups.categories || []).map((c) => `<option value="${c.id}" ${c.id == selected ? 'selected' : ''}>${esc(c.name)}</option>`).join('');
  }

  function departmentOptions(selected) {
    return `<option value="">-- No department --</option>` + (state.lookups.departments || []).map((d) => `<option value="${d.id}" ${d.id == selected ? 'selected' : ''}>${esc(d.name)}</option>`).join('');
  }

  function costCenterOptions(selected) {
    return `<option value="">-- No cost center --</option>` + (state.lookups.cost_centers || []).map((c) => `<option value="${c.id}" ${c.id == selected ? 'selected' : ''}>${esc(c.name)}</option>`).join('');
  }

  async function renderReportForm() {
    const me = state.user;
    main.innerHTML = `
      <div class="panel">
        <h2>Create expense report</h2>
        <form id="report-form">
          <div class="form-row wide">
            <label>Title *</label>
            <input name="title" required />
          </div>
          <div class="form-row wide">
            <label>Purpose</label>
            <textarea name="purpose"></textarea>
          </div>
          <div class="form-row">
            <div><label>Department</label><select name="department_id">${departmentOptions(me.department_id)}</select></div>
            <div><label>Cost center</label><select name="cost_center_id">${costCenterOptions(me.cost_center_id)}</select></div>
          </div>
          <h3>Expense lines</h3>
          <div id="lines"></div>
          <div class="actions"><button type="button" class="secondary" id="add-line">+ Add line</button></div>
          <div class="actions"><button type="submit">Save report</button> <a href="#reports" class="button secondary" style="text-decoration:none">Cancel</a></div>
        </form>
      </div>`;
    const linesEl = document.getElementById('lines');
    function lineRow() {
      const row = document.createElement('div');
      row.className = 'form-row';
      row.innerHTML = `
        <div><label>Category *</label><select class="cat">${categoryOptions()}</select></div>
        <div><label>Date *</label><input class="date" type="date" required /></div>
        <div><label>Description *</label><input class="desc" required /></div>
        <div><label>Merchant</label><input class="merchant" /></div>
        <div><label>Amount *</label><input class="amount" type="number" min="0.01" step="0.01" required /></div>
        <div><button type="button" class="danger small remove-line">Remove</button></div>`;
      row.querySelector('.remove-line').addEventListener('click', () => row.remove());
      linesEl.appendChild(row);
    }
    lineRow();
    document.getElementById('add-line').addEventListener('click', lineRow);
    document.getElementById('report-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const f = e.target;
      const lines = [...linesEl.querySelectorAll('.form-row')].map((row) => ({
        category_id: Number(row.querySelector('.cat').value),
        expense_date: row.querySelector('.date').value,
        description: row.querySelector('.desc').value.trim(),
        merchant: row.querySelector('.merchant').value.trim(),
        amount: Number(row.querySelector('.amount').value),
      }));
      try {
        const report = await API.post('/api/exp/expense_report_creation', {
          title: f.title.value,
          purpose: f.purpose.value,
          department_id: Number(f.department_id.value || 0) || null,
          cost_center_id: Number(f.cost_center_id.value || 0) || null,
          lines,
        });
        toast(`Report ${report.report_no} created`);
        location.hash = `#reports/detail/${report.id}`;
      } catch (err) { toast(err.message, true); }
    });
  }

  async function renderReportDetail(id) {
    const data = await API.get(`/api/exp/expense_report_creation/${id}`);
    const r = data;
    const isOwner = r.employee && r.employee.id === state.user.id;
    const canSubmit = isOwner && r.status === 'draft' && r.total_amount > 0;
    const canRecall = isOwner && r.status === 'submitted';
    const managerDecision = (state.user.role === 'manager' || state.user.role === 'admin') && r.status === 'submitted';
    const financeDecision = (state.user.role === 'finance' || state.user.role === 'admin') && (r.status === 'approved' || r.status === 'finance_approved');
    main.innerHTML = `
      <div class="panel">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
          <h2 style="margin:0">${esc(r.title)}</h2>
          <span>${badge(r.status)}</span>
        </div>
        <p class="muted">Report ${esc(r.report_no)} &middot; Created ${dt(r.created_at)} &middot; Employee: ${esc(r.employee ? r.employee.full_name : '')}</p>
        ${r.purpose ? `<p>${esc(r.purpose)}</p>` : ''}
        <p><strong>Total:</strong> ${money(r.total_amount)}</p>
        ${(r.policy_warnings || []).length ? `<h3>Policy warnings</h3>${r.policy_warnings.map((w) => `<div class="warning-item">${esc(w.message)}</div>`).join('')}` : ''}
        <h3>Expense lines</h3>
        <table>
          <thead><tr><th>Category</th><th>Date</th><th>Description</th><th>Merchant</th><th>Amount</th><th>Receipt</th></tr></thead>
          <tbody>
            ${r.lines.map((l) => `
              <tr>
                <td>${esc(l.category_name)}</td>
                <td>${esc(l.expense_date)}</td>
                <td>${esc(l.description)}</td>
                <td class="muted">${esc(l.merchant || '')}</td>
                <td>${money(l.amount)}</td>
                <td>${l.receipt_id ? `<a href="/api/files/${l.receipt_id}/download" class="button small secondary">Receipt</a>` : `<span class="muted">none</span>`}</td>
              </tr>`).join('')}
          </tbody>
        </table>
        <h3>Receipts</h3>
        <ul class="line-items">
          ${r.receipts.length ? r.receipts.map((x) => `<li><a href="/api/files/${x.file_id}/download">${esc(x.original_name)}</a> <span class="muted">(${esc(x.mime_type)}, ${(x.size / 1024).toFixed(1)} KB)</span></li>`).join('') : '<li class="muted">No receipts attached</li>'}
        </ul>
        ${isOwner ? `<form id="receipt-form" style="margin-top:10px"><div class="form-row"><div><input type="file" name="file" accept=".png,.jpg,.jpeg,.pdf,.txt" required /></div><div><button type="submit" class="secondary">Upload receipt</button></div></div></form>` : ''}
        <div class="actions">
          ${canSubmit ? `<button id="submit-btn">Submit for approval</button>` : ''}
          ${canRecall ? `<button id="recall-btn" class="secondary">Recall to draft</button>` : ''}
          ${managerDecision ? `
            <button id="ap-approve" class="secondary">Approve</button>
            <button id="ap-changes" class="secondary">Request changes</button>
            <button id="ap-reject" class="danger">Reject</button>` : ''}
          ${financeDecision ? `
            <button id="fin-approve" class="secondary">Finance approve</button>
            <button id="fin-reimburse" class="secondary">Mark reimbursed</button>
            <button id="fin-reject" class="danger">Finance reject</button>` : ''}
          <a href="#reports" class="button secondary" style="text-decoration:none">Back</a>
        </div>
        <div id="decision-note" class="form-row wide" style="margin-top:8px;display:none"><textarea id="decision-comment" placeholder="Optional comment on decision"></textarea></div>
      </div>
      <div class="panel">
        <h2>Comments and activity</h2>
        <div id="comments-box"></div>
        <form id="comment-form" class="form-row wide" style="margin-top:10px">
          <input id="comment-text" placeholder="Add a comment..." required />
          <button type="submit" class="secondary">Comment</button>
        </form>
      </div>`;

    const comments = await API.get(`/api/exp/comments_and_activity?report_id=${r.id}`);
    const commentsBox = document.getElementById('comments-box');
    commentsBox.innerHTML =
      `<h3>Comments</h3>` +
      (comments.comments.length ? comments.comments.map((c) => `<div class="comment"><strong>${esc(c.author_name)}</strong> <span class="muted">${dt(c.created_at)}</span><br/>${esc(c.body)}</div>`).join('') : '<p class="muted">No comments yet.</p>') +
      `<h3>Activity</h3>` +
      (comments.activity.length ? comments.activity.map((a) => `<div class="activity">${esc(a.actor_name || 'system')} - ${esc(a.message)} (${dt(a.created_at)})</div>`).join('') : '<p class="muted">No activity.</p>');

    document.getElementById('comment-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await API.post('/api/exp/comments_and_activity', { report_id: r.id, body: document.getElementById('comment-text').value });
        document.getElementById('comment-text').value = '';
        location.reload();
      } catch (err) { toast(err.message, true); }
    });

    if (isOwner) {
      document.getElementById('receipt-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = new FormData();
        form.append('report_id', r.id);
        form.append('file', e.target.file.files[0]);
        try {
          await API.postForm('/api/exp/receipt_upload', form);
          toast('Receipt uploaded');
          location.reload();
        } catch (err) { toast(err.message, true); }
      });
    }

    const decision = (id, action) => {
      const note = document.getElementById('decision-note');
      const comment = document.getElementById('decision-comment');
      note.style.display = 'block';
      document.getElementById('decision-note').dataset.action = action;
      note.dataset.target = id;
      comment.placeholder = `Comment (optional) for ${action}...`;
    };
    const submitDecision = async (targetId, action) => {
      const comment = document.getElementById('decision-comment').value;
      try {
        if (action === 'ap-approve' || action === 'ap-reject' || action === 'ap-changes') {
          await API.post('/api/exp/manager_approval', { report_id: Number(targetId), decision: action === 'ap-approve' ? 'approved' : action === 'ap-reject' ? 'rejected' : 'changes_requested', comment });
        } else {
          await API.post('/api/exp/finance_review', { report_id: Number(targetId), decision: action === 'fin-approve' ? 'approved' : action === 'fin-reject' ? 'rejected' : 'reimbursed', comment });
        }
        toast('Decision recorded');
        location.hash = `#reports/detail/${targetId}`;
        location.reload();
      } catch (err) { toast(err.message, true); }
    };
    if (canSubmit) document.getElementById('submit-btn').addEventListener('click', async () => {
      try {
        const result = await API.post('/api/exp/report_submission', { report_id: r.id });
        const warns = (result.policy_warnings || []).map((w) => w.message).join('; ');
        toast(warns ? `Submitted. Warnings: ${warns}` : 'Submitted for approval', !!warns.length);
        location.hash = `#reports/detail/${r.id}`;
        location.reload();
      } catch (err) { toast(err.message, true); }
    });
    if (canRecall) document.getElementById('recall-btn').addEventListener('click', async () => {
      try { await API.patch(`/api/exp/report_submission/${r.id}`, { action: 'recall' }); toast('Recalled to draft'); location.reload(); } catch (err) { toast(err.message, true); }
    });
    if (managerDecision) {
      for (const id of ['ap-approve', 'ap-changes', 'ap-reject']) document.getElementById(id).addEventListener('click', () => decision(r.id, id));
      for (const id of ['fin-approve', 'fin-reimburse', 'fin-reject']) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', () => decision(r.id, id));
      }
    }
    if (financeDecision) {
      for (const id of ['fin-approve', 'fin-reimburse', 'fin-reject']) document.getElementById(id).addEventListener('click', () => decision(r.id, id));
    }
    if (document.getElementById('decision-comment')) {
      document.getElementById('decision-comment').addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
          const note = document.getElementById('decision-note');
          submitDecision(note.dataset.target, note.dataset.action);
        }
      });
    }
    document.getElementById('decision-note');
  }

  async function renderApprovals() {
    const data = await API.get('/api/exp/manager_approval');
    main.innerHTML = `
      <div class="panel">
        <h2>Manager approval queue</h2>
        <table>
          <thead><tr><th>Report</th><th>Title</th><th>Employee</th><th>Total</th><th>Submitted</th><th>Comments</th><th></th></tr></thead>
          <tbody>
            ${data.queue.map((r) => `
              <tr>
                <td class="mono">${esc(r.report_no)}</td>
                <td>${esc(r.title)}</td>
                <td>${esc(r.employee_name)}</td>
                <td>${money(r.total_amount)}</td>
                <td class="muted">${dt(r.submitted_at)}</td>
                <td>${r.comment_count}</td>
                <td><a href="#reports/detail/${r.id}" class="button small secondary">Review</a></td>
              </tr>`).join('')}
          </tbody>
        </table>
        ${data.queue.length ? '' : '<p class="muted">No reports awaiting your approval.</p>'}
      </div>`;
  }

  async function renderFinance() {
    const data = await API.get('/api/exp/finance_review');
    main.innerHTML = `
      <div class="panel">
        <h2>Finance review queue</h2>
        <table>
          <thead><tr><th>Report</th><th>Title</th><th>Employee</th><th>Total</th><th>Status</th><th>Approved by</th><th></th></tr></thead>
          <tbody>
            ${data.queue.map((r) => `
              <tr>
                <td class="mono">${esc(r.report_no)}</td>
                <td>${esc(r.title)}</td>
                <td>${esc(r.employee_name)}</td>
                <td>${money(r.total_amount)}</td>
                <td>${badge(r.status)}</td>
                <td class="muted">${esc(r.approved_by || '')}</td>
                <td><a href="#reports/detail/${r.id}" class="button small secondary">Review</a></td>
              </tr>`).join('')}
          </tbody>
        </table>
        ${data.queue.length ? '' : '<p class="muted">No reports awaiting finance review.</p>'}
      </div>
      <div class="panel">
        <h2>Export approved reimbursement batch</h2>
        <p class="muted">Generates a CSV of approved / finance-approved / reimbursed reports.</p>
        <button id="export-btn" class="secondary">Generate CSV export</button>
        <div id="export-result"></div>
      </div>`;
    document.getElementById('export-btn').addEventListener('click', async () => {
      try {
        const result = await API.post('/api/exp/reimbursement_export', {});
        const box = document.getElementById('export-result');
        box.innerHTML = `<p class="success">Export <code>${esc(result.batch.batch_no)}</code> generated with ${result.batch.row_count} rows.</p><p><a href="${result.download_url}" class="button small secondary">Download CSV</a></p>`;
        toast('Export generated');
      } catch (err) { toast(err.message, true); }
    });
  }

  async function renderPolicies() {
    const data = await API.get('/api/exp/policy_rules');
    const canManage = ['admin', 'manager'].includes(state.user.role);
    main.innerHTML = `
      <div class="panel">
        <h2>Policy rules</h2>
        <table>
          <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Category</th><th>Threshold</th><th>Receipt</th><th>Enabled</th></tr></thead>
          <tbody>
            ${data.rules.map((r) => `
              <tr>
                <td class="mono">${esc(r.code)}</td>
                <td>${esc(r.name)}</td>
                <td class="muted">${esc(r.rule_type)}</td>
                <td>${esc(r.category_name || '')}</td>
                <td>${r.amount_threshold !== null ? money(r.amount_threshold) : ''}</td>
                <td>${r.requires_receipt === null ? '' : r.requires_receipt ? 'yes' : 'no'}</td>
                <td>${r.enabled ? 'yes' : 'no'}</td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>
      ${canManage ? `
      <div class="panel">
        <h2>Add policy rule</h2>
        <form id="policy-form">
          <div class="form-row">
            <div><label>Code *</label><input name="code" required placeholder="POL-XXX" /></div>
            <div><label>Name *</label><input name="name" required /></div>
            <div><label>Rule type *</label><select name="rule_type">${data.types.map((t) => `<option value="${t}">${esc(t)}</option>`).join('')}</select></div>
          </div>
          <div class="form-row">
            <div><label>Category</label><select name="category_id"><option value="">-- none --</option>${categoryOptions()}</select></div>
            <div><label>Amount threshold</label><input name="amount_threshold" type="number" min="0" step="0.01" /></div>
            <div><label>Requires receipt</label><select name="requires_receipt"><option value="1">yes</option><option value="0">no</option></select></div>
          </div>
          <div class="actions"><button type="submit">Create rule</button></div>
        </form>
      </div>` : ''}`;
    if (canManage) {
      document.getElementById('policy-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        try {
          await API.post('/api/exp/policy_rules', {
            code: f.code.value.trim(),
            name: f.name.value.trim(),
            rule_type: f.rule_type.value,
            category_id: Number(f.category_id.value || 0) || null,
            amount_threshold: f.amount_threshold.value ? Number(f.amount_threshold.value) : undefined,
            requires_receipt: Number(f.requires_receipt.value) ? true : false,
          });
          toast('Policy rule created');
          location.reload();
        } catch (err) { toast(err.message, true); }
      });
    }
  }

  async function renderExports() {
    const data = await API.get('/api/exp/reimbursement_export');
    main.innerHTML = `
      <div class="panel">
        <h2>Reimbursement export batches</h2>
        <button id="export-btn" class="secondary">Generate new CSV export</button>
        <div id="export-result"></div>
        <table style="margin-top:14px">
          <thead><tr><th>Batch</th><th>Status</th><th>Rows</th><th>Created by</th><th>Created</th><th>File</th></tr></thead>
          <tbody>
            ${data.batches.map((b) => `
              <tr>
                <td class="mono">${esc(b.batch_no)}</td>
                <td>${badge(b.status)}</td>
                <td>${b.row_count}</td>
                <td>${esc(b.created_by_name)}</td>
                <td class="muted">${dt(b.created_at)}</td>
                <td>${b.file_id ? `<a href="/api/files/${b.file_id}/download" class="button small secondary">${esc(b.original_name)}</a>` : ''}</td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>`;
    document.getElementById('export-btn').addEventListener('click', async () => {
      try {
        const result = await API.post('/api/exp/reimbursement_export', {});
        document.getElementById('export-result').innerHTML = `<p class="success">Exported <code>${esc(result.batch.batch_no)}</code> (${result.batch.row_count} rows). <a href="${result.download_url}" class="button small secondary">Download</a></p>`;
        toast('Export generated');
      } catch (err) { toast(err.message, true); }
    });
  }

  async function renderAdmin() {
    if (state.user.role !== 'admin') { main.innerHTML = '<div class="alert error">Access denied</div>'; return; }
    const data = await API.get('/api/exp/admin_configuration');
    main.innerHTML = `
      <div class="panel">
        <h2>Admin configuration</h2>
        <div class="tabs"><button data-tab="departments" class="secondary">Departments</button><button data-tab="costcenters" class="secondary">Cost centers</button><button data-tab="categories" class="secondary">Categories</button><button data-tab="users" class="secondary">Users</button><button data-tab="audit" class="secondary">Audit trail</button></div>
        <div id="admin-tab"></div>
      </div>`;
    const tab = document.getElementById('admin-tab');
    function showTab(name) {
      const headers = { departments: ['Code', 'Name'], costcenters: ['Code', 'Name', 'Department'], categories: ['Code', 'Name', 'Receipt required'], users: ['Username', 'Name', 'Role', 'Department', 'Manager', 'Active'] };
      const rows = {
        departments: data.departments.map((d) => [esc(d.code), esc(d.name)]),
        costcenters: data.cost_centers.map((c) => [esc(c.code), esc(c.name), esc(c.department_name || '')]),
        categories: data.categories.map((c) => [esc(c.code), esc(c.name), c.requires_receipt ? 'yes' : 'no']),
        users: data.users.map((u) => [esc(u.username), esc(u.full_name), badge(u.role), esc(u.department_name || ''), esc(u.manager_name || ''), u.active ? 'yes' : 'no']),
      };
      if (name === 'audit') {
        API.get('/api/meta/audit').then((a) => {
          tab.innerHTML = `<table><thead><tr><th>#</th><th>Actor</th><th>Action</th><th>Entity</th><th>Time</th></tr></thead><tbody>` +
            a.events.map((e) => `<tr><td>${e.id}</td><td>${esc(e.username || 'system')}</td><td class="mono">${esc(e.action)}</td><td class="muted">${esc(e.entity_type || '')}${e.entity_id ? ' #' + e.entity_id : ''}</td><td class="muted">${dt(e.created_at)}</td></tr>`).join('') +
            `</tbody></table>`;
        }).catch((err) => { tab.innerHTML = `<div class="alert error">${esc(err.message)}</div>`; });
        return;
      }
      tab.innerHTML = `<table><thead><tr>${headers[name].map((h) => `<th>${h}</th>`).join('')}</tr></thead><tbody>${rows[name].map((r) => `<tr>${r.map((c) => `<td>${c}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
    }
    document.querySelectorAll('.tabs button').forEach((b) => b.addEventListener('click', () => showTab(b.dataset.tab)));
    showTab('departments');
  }

  async function renderAccount() {
    const data = await API.get('/api/exp/account_access');
    const a = data.account;
    main.innerHTML = `
      <div class="panel">
        <h2>Account</h2>
        <div class="form-row">
          <div><label>Username</label><input value="${esc(a.username)}" disabled /></div>
          <div><label>Full name</label><input id="acc-name" value="${esc(a.full_name)}" /></div>
          <div><label>Email</label><input id="acc-email" value="${esc(a.email)}" /></div>
        </div>
        <div class="actions"><button id="acc-save" class="secondary">Save profile</button></div>
      </div>
      <div class="panel">
        <h2>Change password</h2>
        <div class="form-row">
          <div><label>Current password</label><input id="pw-old" type="password" /></div>
          <div><label>New password</label><input id="pw-new" type="password" /></div>
        </div>
        <div class="actions"><button id="pw-save" class="secondary">Update password</button></div>
      </div>
      <div class="panel">
        <h2>Active sessions</h2>
        <table><thead><tr><th>Session</th><th>Created</th><th>Expires</th><th>IP</th><th></th></tr></thead><tbody>
          ${data.sessions.map((s) => `<tr><td class="mono">${esc(s.id.slice(0, 8))}...</td><td class="muted">${dt(s.created_at)}</td><td class="muted">${dt(s.expires_at)}</td><td class="muted">${esc(s.ip || '')}</td><td><button class="danger small revoke" data-sess="${esc(s.id)}">Revoke</button></td></tr>`).join('')}
        </tbody></table>
      </div>`;
    document.getElementById('acc-save').addEventListener('click', async () => {
      try {
        await API.patch('/api/exp/account_access/self', { full_name: document.getElementById('acc-name').value, email: document.getElementById('acc-email').value });
        toast('Profile saved'); location.reload();
      } catch (err) { toast(err.message, true); }
    });
    document.getElementById('pw-save').addEventListener('click', async () => {
      try {
        await API.patch('/api/exp/account_access/self', { old_password: document.getElementById('pw-old').value, new_password: document.getElementById('pw-new').value });
        toast('Password updated'); document.getElementById('pw-old').value = ''; document.getElementById('pw-new').value = '';
      } catch (err) { toast(err.message, true); }
    });
    document.querySelectorAll('.revoke').forEach((b) => b.addEventListener('click', async () => {
      try { await API.patch(`/api/exp/account_access/${b.dataset.sess}`, { action: 'revoke' }); toast('Session revoked'); location.reload(); } catch (err) { toast(err.message, true); }
    }));
  }

  window.addEventListener('DOMContentLoaded', boot);
})();
