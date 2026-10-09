import { api, el, showNotice, statusBadge } from './api.js';

const STATE = {
  session: null,
  section: 'reports',
  ws: null
};

const NAV_ITEMS = [
  { id: 'reports', label: 'My Reports', roles: ['employee', 'manager', 'finance', 'admin'] },
  { id: 'create', label: 'Create Report', roles: ['employee', 'admin'] },
  { id: 'approval', label: 'Manager Approval', roles: ['manager', 'admin'] },
  { id: 'review', label: 'Finance Review', roles: ['finance', 'admin'] },
  { id: 'policy', label: 'Policy Rules', roles: ['admin', 'manager'] },
  { id: 'employees', label: 'Employees', roles: ['employee', 'manager', 'finance', 'admin'] },
  { id: 'export', label: 'Reimbursement Export', roles: ['finance', 'admin'] },
  { id: 'admin', label: 'Admin', roles: ['admin'] },
  { id: 'integration', label: 'API Integration', roles: ['employee', 'manager', 'finance', 'admin'] }
];

async function refreshSession() {
  try {
    const res = await api('GET', '/account_access');
    STATE.session = res.authenticated ? res : null;
  } catch (_) {
    STATE.session = null;
  }
}

function renderNav() {
  const nav = document.getElementById('nav');
  nav.innerHTML = '';
  const role = STATE.session.user.role;
  for (const item of NAV_ITEMS.filter(i => i.roles.includes(role))) {
    const btn = el('button', {
      class: item.id === STATE.section ? 'active' : '',
      onclick: () => navigate(item.id)
    }, [item.label]);
    nav.appendChild(btn);
  }
}

async function navigate(section) {
  STATE.section = section;
  renderNav();
  const title = document.getElementById('sectionTitle');
  const content = document.getElementById('content');
  content.innerHTML = '';
  const handler = SECTIONS[section];
  if (handler) {
    title.textContent = NAV_ITEMS.find(n => n.id === section)?.label || section;
    await handler(content);
  } else {
    title.textContent = 'Not implemented';
  }
}

const SECTIONS = {};

SECTIONS.reports = async (root) => {
  const data = await api('GET', '/expense_report_creation');
  const wrap = el('div', { class: 'card' }, [
    el('h3', {}, ['Expense Reports']),
    renderReportTable(data.reports, { showEmployee: STATE.session.user.role !== 'employee' })
  ]);
  root.appendChild(wrap);
};

function renderReportTable(reports, opts = {}) {
  if (!reports.length) {
    return el('p', {}, ['No reports yet.']);
  }
  const table = el('table', {}, [
    el('thead', {}, [el('tr', {}, [
      el('th', {}, ['Code']),
      el('th', {}, ['Title']),
      opts.showEmployee ? el('th', {}, ['Employee']) : null,
      el('th', {}, ['Total']),
      el('th', {}, ['Status']),
      el('th', {}, ['Submitted']),
      el('th', {}, [''])
    ].filter(Boolean))]),
    el('tbody', {}, reports.map(r => el('tr', {}, [
      el('td', {}, [r.report_code]),
      el('td', {}, [r.title]),
      opts.showEmployee ? el('td', {}, [r.employee_username || '']) : null,
      el('td', {}, [Number(r.total_amount || 0).toFixed(2) + ' ' + (r.currency || 'USD')]),
      el('td', {}, [statusBadge(r.status)]),
      el('td', {}, [r.submitted_at || '-']),
      el('td', {}, [
        el('button', {
          class: 'btn secondary',
          onclick: () => openReport(r.id)
        }, ['Open'])
      ])
    ].filter(Boolean))))
  ]);
  return table;
}

async function openReport(id) {
  const detail = await api('GET', `/expense_report_creation/${id}`);
  const data = detail;
  const modal = el('div', { class: 'card', id: `report-detail-${id}` }, [
    el('h3', {}, [`Report ${data.report.report_code} — ${data.report.title}`]),
    el('p', {}, [
      el('span', { class: 'tag' }, [`Status: ${data.report.status}`]),
      el('span', { class: 'tag' }, [`Total: ${data.report.total_amount} ${data.report.currency}`])
    ]),
    el('p', {}, [data.report.description || '']),
    renderLines(id, data.lines, data.report.status),
    el('h4', {}, ['Comments']),
    renderComments(id, data.report),
    el('button', { class: 'btn secondary', onclick: () => document.getElementById(`report-detail-${id}`).remove() }, ['Close'])
  ]);
  document.getElementById('content').prepend(modal);
}

function renderLines(reportId, lines, status) {
  if (!lines || !lines.length) return el('p', {}, ['No lines.']);
  const wrap = el('div', {}, [el('h4', {}, ['Lines'])]);
  const table = el('table', {}, [
    el('thead', {}, [el('tr', {}, [
      el('th', {}, ['Date']), el('th', {}, ['Category']), el('th', {}, ['Description']),
      el('th', {}, ['Merchant']), el('th', {}, ['Amount']), el('th', {}, ['Receipt'])
    ])]),
    el('tbody', {}, lines.map(l => el('tr', {}, [
      el('td', {}, [l.expense_date]), el('td', {}, [l.category_code || '-']),
      el('td', {}, [l.description]), el('td', {}, [l.merchant || '-']),
      el('td', {}, [Number(l.amount).toFixed(2)]),
      el('td', {}, [l.receipt_filename ? el('a', { href: `/api/exp/receipt_upload/file/${l.receipt_id}`, target: '_blank' }, [l.receipt_filename]) : '-'])
    ])))
  ]);
  wrap.appendChild(table);
  return wrap;
}

async function renderComments(reportId, report) {
  const data = await api('GET', `/comments_and_activity?reportId=${reportId}`).catch(() => ({ comments: [], activity: [] }));
  const container = el('div', {}, [
    el('ul', { class: 'list' }, (data.comments || []).map(c => el('li', {}, [
      el('strong', {}, [`${c.full_name || c.username}: `]),
      c.body,
      el('small', {}, [` — ${c.created_at}`])
    ])))
  ]);
  const form = el('form', {
    onsubmit: async (ev) => {
      ev.preventDefault();
      const ta = ev.target.elements.body;
      const text = ta.value.trim();
      if (!text) return;
      try {
        await api('POST', '/comments_and_activity', { reportId, body: text });
        ta.value = '';
        renderComments(reportId, report);
      } catch (err) { showNotice(container, err.message, 'error'); }
    }
  }, [
    el('textarea', { name: 'body', placeholder: 'Add comment...' }),
    el('p', {}, [el('button', { class: 'btn', type: 'submit' }, ['Post'])])
  ]);
  const activity = el('details', {}, [
    el('summary', {}, ['Activity log']),
    el('ul', { class: 'list' }, (data.activity || []).map(a => el('li', {}, [`${a.event_type}${a.details ? ' ' + JSON.parse(a.details || '{}').note || '' : ''} — ${a.created_at}`])))
  ]);
  return el('div', {}, [container, form, activity]);
}

SECTIONS.create = async (root) => {
  const data = await api('GET', '/expense_report_creation');
  const card = el('div', { class: 'card' }, [el('h3', {}, ['Create Draft Report'])]);
  const form = el('form', {
    onsubmit: async (ev) => {
      ev.preventDefault();
      const title = ev.target.elements.title.value.trim();
      if (!title) return;
      try {
        const out = await api('POST', '/expense_report_creation', { title, description: ev.target.elements.description.value });
        ev.target.reset();
        await openReport(out.id);
      } catch (err) { showNotice(card, err.message, 'error'); }
    }
  }, [
    el('label', {}, ['Title']), el('input', { type: 'text', name: 'title', required: true }),
    el('label', {}, ['Description']), el('textarea', { name: 'description' }),
    el('p', {}, [el('button', { class: 'btn', type: 'submit' }, ['Create draft'])])
  ]);
  card.appendChild(form);
  root.appendChild(card);

  const uploadSection = el('div', { class: 'card' }, [el('h3', {}, ['Upload Receipt (to a draft report)')]);
  const upForm = el('form', {
    onsubmit: async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.target);
      try {
        const out = await api('POST', '/receipt_upload', fd);
        showNotice(root, `Uploaded ${out.filename} (id ${out.fileId})`, 'success');
        ev.target.reset();
      } catch (err) { showNotice(root, err.message, 'error'); }
    }
  }, [
    el('label', {}, ['Report ID']), el('input', { type: 'number', name: 'reportId', required: true }),
    el('label', {}, ['Receipt file']), el('input', { type: 'file', name: 'file', required: true }),
    el('p', {}, [el('button', { class: 'btn', type: 'submit' }, ['Upload'])])
  ]);
  uploadSection.appendChild(upForm);
  root.appendChild(uploadSection);
};

SECTIONS.approval = async (root) => {
  const data = await api('GET', '/manager_approval?status=submitted');
  const wrap = el('div', { class: 'card' }, [el('h3', {}, ['Pending Manager Approvals'])]);
  if (!data.reports.length) {
    wrap.appendChild(el('p', {}, ['No submitted reports.']));
  } else {
    const table = el('table', {}, [
      el('thead', {}, [el('tr', {}, [
        el('th', {}, ['Code']), el('th', {}, ['Title']), el('th', {}, ['Employee']),
        el('th', {}, ['Total']), el('th', {}, ['Submitted'])
      ])]),
      el('tbody', {}, data.reports.map(r => el('tr', {}, [
        el('td', {}, [r.report_code]),
        el('td', {}, [r.title]),
        el('td', {}, [r.employee_name || r.employee_username]),
        el('td', {}, [Number(r.total_amount || 0).toFixed(2)]),
        el('td', {}, [r.submitted_at || '-'])
      ])))
    ]);
    wrap.appendChild(table);
    data.reports.forEach(r => {
      const form = el('form', {
        onsubmit: async (ev) => {
          ev.preventDefault();
          const decision = ev.target.elements.decision.value;
          const note = ev.target.elements.note.value;
          try {
            await api('POST', '/manager_approval', { reportId: r.id, decision, note });
            showNotice(root, `Decision ${decision} recorded for ${r.report_code}`, 'success');
            navigate('approval');
          } catch (err) { showNotice(root, err.message, 'error'); }
        }
      }, [
        el('p', {}, [el('strong', {}, [r.report_code]), ' — ', r.title]),
        el('label', {}, ['Decision']),
        el('select', { name: 'decision' }, [
          el('option', { value: 'approved' }, ['approve']),
          el('option', { value: 'changes_requested' }, ['request changes']),
          el('option', { value: 'rejected' }, ['reject'])
        ]),
        el('label', {}, ['Note']), el('input', { type: 'text', name: 'note' }),
        el('p', {}, [el('button', { class: 'btn', type: 'submit' }, ['Submit decision'])])
      ]);
      wrap.appendChild(form);
    });
  }
  root.appendChild(wrap);

  if (data.history && data.history.length) {
    const hist = el('div', { class: 'card' }, [el('h3', {}, ['Recent decisions'])]);
    hist.appendChild(el('table', {}, [
      el('thead', {}, [el('tr', {}, [el('th', {}, ['When']), el('th', {}, ['Manager']), el('th', {}, ['Decision']), el('th', {}, ['Note'])])]),
      el('tbody', {}, data.history.map(h => el('tr', {}, [
        el('td', {}, [h.created_at]),
        el('td', {}, [h.manager_username]),
        el('td', {}, [h.decision]),
        el('td', {}, [h.note || '-'])
      ])))
    ]));
    root.appendChild(hist);
  }
};

SECTIONS.review = async (root) => {
  const data = await api('GET', '/finance_review?status=manager_approved');
  const wrap = el('div', { class: 'card' }, [el('h3', {}, ['Awaiting Finance Review'])]);
  if (!data.reports.length) {
    wrap.appendChild(el('p', {}, ['Nothing to review.']));
  } else {
    data.reports.forEach(r => {
      const form = el('form', {
        onsubmit: async (ev) => {
          ev.preventDefault();
          const decision = ev.target.elements.decision.value;
          const note = ev.target.elements.note.value;
          try {
            const out = await api('POST', '/finance_review', { reportId: r.id, decision, note });
            showNotice(root, `Finance decision ${decision} recorded. Batch ${out.batchId || 'n/a'}`, 'success');
            navigate('review');
          } catch (err) { showNotice(root, err.message, 'error'); }
        }
      }, [
        el('p', {}, [`${r.report_code} — ${r.title} (${r.employee_name})`]),
        el('label', {}, ['Decision']),
        el('select', { name: 'decision' }, [
          el('option', { value: 'approved' }, ['approve for reimbursement']),
          el('option', { value: 'paid' }, ['mark paid']),
          el('option', { value: 'rejected' }, ['reject']),
          el('option', { value: 'on_hold' }, ['on hold'])
        ]),
        el('label', {}, ['Note']), el('input', { type: 'text', name: 'note' }),
        el('p', {}, [el('button', { class: 'btn', type: 'submit' }, ['Submit'])])
      ]);
      wrap.appendChild(form);
    });
  }
  root.appendChild(wrap);
  const exp = el('div', { class: 'card' }, [el('h3', {}, ['Recent finance history'])]);
  exp.appendChild(el('ul', { class: 'list' }, data.history.map(h => el('li', {}, [`${h.finance_username} (${h.report_code}) — ${h.decision}${h.batch_id ? ' #' + h.batch_id : ''} ${h.note || ''}`]))));
  root.appendChild(exp);
};

SECTIONS.policy = async (root) => {
  const data = await api('GET', '/policy_rules');
  const wrap = el('div', { class: 'card' }, [
    el('h3', {}, ['Policy Rules']),
    el('table', {}, [
      el('thead', {}, [el('tr', {}, [el('th', {}, ['Name']), el('th', {}, ['Category']), el('th', {}, ['Cap']), el('th', {}, ['Receipt above']), el('th', {}, ['Block?']), el('th', {}, ['Active'])])]),
      el('tbody', {}, data.rules.map(r => el('tr', {}, [
        el('td', {}, [r.name]), el('td', {}, [r.category_code || 'any']),
        el('td', {}, [r.max_amount == null ? '-' : r.max_amount]),
        el('td', {}, [r.require_receipt_above]),
        el('td', {}, [r.block_when_exceeded ? 'yes' : 'no']),
        el('td', {}, [r.active ? 'yes' : 'no'])
      ])))
    ])
  ]);
  root.appendChild(wrap);

  if (STATE.session.user.role === 'admin') {
    const card = el('div', { class: 'card' }, [el('h3', {}, ['Add policy rule'])]);
    card.appendChild(el('form', {
      onsubmit: async (ev) => {
        ev.preventDefault();
        const body = Object.fromEntries(new FormData(ev.target).entries());
        try {
          await api('POST', '/policy_rules', {
            name: body.name,
            description: body.description,
            categoryCode: body.categoryCode,
            maxAmount: body.maxAmount ? Number(body.maxAmount) : null,
            requireReceiptAbove: Number(body.requireReceiptAbove || 0),
            blockWhenExceeded: body.blockWhenExceeded === 'on'
          });
          ev.target.reset();
          navigate('policy');
        } catch (err) { showNotice(card, err.message, 'error'); }
      }
    }, [
      el('label', {}, ['Name']), el('input', { type: 'text', name: 'name', required: true }),
      el('label', {}, ['Description']), el('input', { type: 'text', name: 'description' }),
      el('label', {}, ['Category code']),
      el('select', { name: 'categoryCode' }, [el('option', { value: '' }, ['any']), ...data.categories.map(c => el('option', { value: c.code }, [c.code]))]),
      el('label', {}, ['Max amount']), el('input', { type: 'number', name: 'maxAmount', step: '0.01' }),
      el('label', {}, ['Receipt required above']), el('input', { type: 'number', name: 'requireReceiptAbove', step: '0.01' }),
      el('label', {}, [el('input', { type: 'checkbox', name: 'blockWhenExceeded' }), ' block when exceeded']),
      el('p', {}, [el('button', { class: 'btn', type: 'submit' }, ['Save'])])
    ]));
    root.appendChild(card);
  }
};

SECTIONS.employees = async (root) => {
  const data = await api('GET', '/employee_data_access');
  const card = el('div', { class: 'card' }, [
    el('h3', {}, ['Visible Employees']),
    el('p', {}, ['Only employees visible to your role are listed.']),
    el('table', {}, [
      el('thead', {}, [el('tr', {}, [
        el('th', {}, ['Username']), el('th', {}, ['Full name']), el('th', {}, ['Role']),
        el('th', {}, ['Department']), el('th', {}, ['Manager'])
      ])]),
      el('tbody', {}, data.employees.map(e => el('tr', {}, [
        el('td', {}, [e.username]),
        el('td', {}, [e.full_name]),
        el('td', {}, [e.role]),
        el('td', {}, [e.department_name || '-']),
        el('td', {}, [e.manager_username || '-'])
      ])))
    ])
  ]);
  root.appendChild(card);
};

SECTIONS.export = async (root) => {
  const card = el('div', { class: 'card' }, [el('h3', {}, ['Generate Reimbursement Export'])]);
  const form = el('form', {
    onsubmit: async (ev) => {
      ev.preventDefault();
      try {
        const out = await api('POST', '/reimbursement_export', { statuses: ['finance_approved','reimbursed','paid'] });
        showNotice(card, `Batch ${out.batchId} created — ${out.recordCount} rows / total ${out.totalAmount.toFixed(2)}`, 'success');
        const a = el('a', { href: out.downloadPath, target: '_blank', class: 'btn secondary' }, ['Download CSV']);
        card.appendChild(a);
        navigate('export');
      } catch (err) { showNotice(card, err.message, 'error'); }
    }
  }, [
    el('p', {}, ['Generates a CSV of reports whose status is finance_approved / reimbursed / paid.']),
    el('p', {}, [el('button', { class: 'btn', type: 'submit' }, ['Generate'])])
  ]);
  card.appendChild(form);
  root.appendChild(card);

  const list = el('div', { class: 'card' }, [el('h3', {}, ['Past exports'])]);
  const data = await api('GET', '/reimbursement_export');
  if (!data.exports.length) list.appendChild(el('p', {}, ['No exports yet.']));
  else {
    list.appendChild(el('table', {}, [
      el('thead', {}, [el('tr', {}, [el('th', {}, ['Batch']), el('th', {}, ['Finance']), el('th', {}, ['Records']), el('th', {}, ['Total']), el('th', {}, [''])])]),
      el('tbody', {}, data.exports.map(e => el('tr', {}, [
        el('td', {}, [e.batch_id]),
        el('td', {}, [e.finance_username]),
        el('td', {}, [String(e.record_count)]),
        el('td', {}, [Number(e.total_amount).toFixed(2)]),
        el('td', {}, [e.file_name ? el('a', { href: `/api/exp/reimbursement_export/download/${e.batch_id}`, class: 'btn secondary' }, ['Download']) : '-'])
      ])))
    ]));
  }
  root.appendChild(list);
};

SECTIONS.admin = async (root) => {
  if (STATE.session.user.role !== 'admin') {
    root.appendChild(el('p', {}, ['Admin role required.']));
    return;
  }
  const data = await api('GET', '/admin_configuration');
  const card = el('div', { class: 'card' }, [
    el('h3', {}, ['Departments']),
    el('ul', { class: 'list' }, data.departments.map(d => el('li', {}, [`${d.name} (${d.code}) — cost center ${d.cost_center}`])))
  ]);
  root.appendChild(card);

  const cats = el('div', { class: 'card' }, [
    el('h3', {}, ['Categories']),
    el('ul', { class: 'list' }, data.categories.map(c => el('li', {}, [`${c.name} (${c.code})`]))),
    el('form', {
      onsubmit: async (ev) => {
        ev.preventDefault();
        const body = Object.fromEntries(new FormData(ev.target).entries());
        try { await api('POST', '/admin_configuration/categories', body); navigate('admin'); }
        catch (err) { showNotice(cats, err.message, 'error'); }
      }
    }, [
      el('label', {}, ['Name']), el('input', { type: 'text', name: 'name', required: true }),
      el('label', {}, ['Code']), el('input', { type: 'text', name: 'code', required: true }),
      el('label', {}, ['Description']), el('input', { type: 'text', name: 'description' }),
      el('p', {}, [el('button', { class: 'btn', type: 'submit' }, ['Add category'])])
    ])
  ]);
  root.appendChild(cats);

  const users = el('div', { class: 'card' }, [
    el('h3', {}, ['Users']),
    el('table', {}, [
      el('thead', {}, [el('tr', {}, [el('th', {}, ['Username']), el('th', {}, ['Name']), el('th', {}, ['Role']), el('th', {}, ['Department'])])]),
      el('tbody', {}, data.users.map(u => el('tr', {}, [
        el('td', {}, [u.username]), el('td', {}, [u.full_name]), el('td', {}, [u.role]), el('td', {}, [u.department_name || '-'])
      ])))
    ]),
    el('form', {
      onsubmit: async (ev) => {
        ev.preventDefault();
        const body = Object.fromEntries(new FormData(ev.target).entries());
        try { await api('POST', '/admin_configuration/users', body); navigate('admin'); }
        catch (err) { showNotice(users, err.message, 'error'); }
      }
    }, [
      el('label', {}, ['Username']), el('input', { type: 'text', name: 'username', required: true }),
      el('label', {}, ['Email']), el('input', { type: 'email', name: 'email', required: true }),
      el('label', {}, ['Full name']), el('input', { type: 'text', name: 'fullName', required: true }),
      el('label', {}, ['Password']), el('input', { type: 'password', name: 'password', required: true }),
      el('label', {}, ['Role']),
      el('select', { name: 'role' }, ['employee','manager','finance','admin'].map(r => el('option', { value: r }, [r]))),
      el('p', {}, [el('button', { class: 'btn', type: 'submit' }, ['Add user'])])
    ])
  ]);
  root.appendChild(users);

  const cfg = el('div', { class: 'card' }, [
    el('h3', {}, ['Configurations']),
    el('ul', { class: 'list' }, data.configs.map(c => el('li', {}, [`${c.config_key} = ${c.config_value}`]))),
    el('form', {
      onsubmit: async (ev) => {
        ev.preventDefault();
        const body = Object.fromEntries(new FormData(ev.target).entries());
        try { await api('PUT', `/admin_configuration/config/${encodeURIComponent(body.config_key)}`, { value: body.value }); navigate('admin'); }
        catch (err) { showNotice(cfg, err.message, 'error'); }
      }
    }, [
      el('label', {}, ['Key']), el('input', { type: 'text', name: 'config_key', required: true }),
      el('label', {}, ['Value']), el('input', { type: 'text', name: 'value', required: true }),
      el('p', {}, [el('button', { class: 'btn', type: 'submit' }, ['Save'])])
    ])
  ]);
  root.appendChild(cfg);

  const audit = el('div', { class: 'card' }, [
    el('h3', {}, ['Audit log (latest)']),
    el('ul', { class: 'list' }, data.audit.map(a => el('li', {}, [`${a.created_at} — ${a.actor || 'anon'} ${a.action} ${a.entity_type}#${a.entity_id || '-'}`])))
  ]);
  root.appendChild(audit);
};

SECTIONS.integration = async (root) => {
  const data = await api('GET', '/frontend_api_integration_and_errors');
  const card = el('div', { class: 'card' }, [el('h3', {}, ['Frontend API State Events'])]);
  card.appendChild(el('p', {}, ['UI may post UI message codes here for observability.']));
  card.appendChild(el('form', {
    onsubmit: async (ev) => {
      ev.preventDefault();
      const body = Object.fromEntries(new FormData(ev.target).entries());
      try {
        await api('POST', '/frontend_api_integration_and_errors', { reportId: Number(body.reportId), code: body.code, uiMessage: body.uiMessage });
        navigate('integration');
      } catch (err) { showNotice(card, err.message, 'error'); }
    }
  }, [
    el('label', {}, ['Report ID']), el('input', { type: 'number', name: 'reportId', required: true }),
    el('label', {}, ['State code']),
    el('select', { name: 'code' }, ['policy_warning','validation_error','conflict','network_error','approval_acknowledged'].map(c => el('option', { value: c }, [c]))),
    el('label', {}, ['UI message']), el('input', { type: 'text', name: 'uiMessage' }),
    el('p', {}, [el('button', { class: 'btn', type: 'submit' }, ['Send'])])
  ]));
  const recent = el('div', {}, [
    el('h4', {}, ['Recent state events']),
    el('ul', { class: 'list' }, data.recentEvents.map(e => el('li', {}, [`${e.created_at} — ${e.username || 'anon'} ${e.event_type} report#${e.report_id}`])))
  ]);
  card.appendChild(recent);
  root.appendChild(card);
};

async function login(username, password) {
  const data = await api('POST', '/account_access/login', { username, password });
  return data;
}

async function logout() {
  await api('POST', '/account_access/logout', {});
  STATE.session = null;
  document.getElementById('appShell').classList.add('hidden');
  document.getElementById('loginView').classList.remove('hidden');
}

async function bootstrap() {
  await refreshSession();
  if (!STATE.session) {
    document.getElementById('loginView').classList.remove('hidden');
    return;
  }
  enterApp();
}

function enterApp() {
  document.getElementById('loginView').classList.add('hidden');
  document.getElementById('appShell').classList.remove('hidden');
  document.getElementById('userBadge').textContent = `${STATE.session.user.fullName} — ${STATE.session.user.role}`;
  renderNav();
  navigate('reports');
  setupWebSocket();
}

function setupWebSocket() {
  if (STATE.ws) STATE.ws.close();
  const proto = location.protocol === 'https:' ? 'wss' : 'ws';
  const ws = new WebSocket(`${proto}://${location.host}/ws/exp`);
  STATE.ws = ws;
  ws.onopen = () => {
    const status = document.getElementById('wsStatus');
    status.textContent = 'WS: connected';
    status.classList.add('connected');
    ws.send(JSON.stringify({ action: 'subscribe', reportId: 'all' }));
  };
  ws.onclose = () => {
    const status = document.getElementById('wsStatus');
    status.textContent = 'WS: disconnected';
    status.classList.remove('connected');
  };
  ws.onerror = () => { /* ignore */ };
  ws.onmessage = (ev) => {
    try {
      const msg = JSON.parse(ev.data);
      if (msg.type === 'report_event') {
        console.log('[ws]', msg);
      }
    } catch (_) { /* ignore */ }
  };
}

document.getElementById('loginForm').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const username = document.getElementById('loginUsername').value.trim();
  const password = document.getElementById('loginPassword').value;
  const errBox = document.getElementById('loginError');
  errBox.classList.add('hidden');
  try {
    await login(username, password);
    await refreshSession();
    enterApp();
  } catch (err) {
    errBox.textContent = err.message;
    errBox.classList.remove('hidden');
  }
});

document.getElementById('logoutBtn').addEventListener('click', logout);

bootstrap();
