(function () {
  'use strict';

  const $ = (sel, el = document) => el.querySelector(sel);
  const $$ = (sel, el = document) => Array.from(el.querySelectorAll(sel));

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

  async function api(path, options = {}) {
    const res = await fetch(path, {
      credentials: 'same-origin',
      headers: options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' },
      ...options,
      body: options.body instanceof FormData ? options.body : options.body ? JSON.stringify(options.body) : undefined,
    });
    let data = null;
    try {
      data = await res.json();
    } catch {
      data = null;
    }
    if (!res.ok) {
      throw new Error(data?.error?.message || `Request failed (${res.status})`);
    }
    return data;
  }

  function toast(message, kind = 'info') {
    const el = document.createElement('div');
    el.className = `toast ${kind}`;
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 4200);
  }

  const state = {
    user: null,
    section: 'dashboard',
    chat: { conversations: [], activeId: null, messages: [], collections: [], configs: [] },
    ws: null,
  };

  const navDefs = [
    { id: 'dashboard', label: 'Dashboard', icon: '⌂' },
    { id: 'chat', label: 'Chat', icon: '✎' },
    { id: 'templates', label: 'Prompt templates', icon: '◧' },
    { id: 'models', label: 'Model config', icon: '⚙' },
    { id: 'knowledge', label: 'Knowledge files', icon: '⬆' },
    { id: 'retrieval', label: 'Retrieval', icon: '⌕' },
    { id: 'tools', label: 'Tools & plugins', icon: '▤' },
    { id: 'keys', label: 'API keys', icon: '⚿' },
    { id: 'shares', label: 'Shares', icon: '⇪' },
    { id: 'logs', label: 'Usage & audit', icon: '≡' },
    { id: 'account', label: 'Account access', icon: '◉' },
  ];

  function renderNav() {
    const nav = $('#nav');
    const items = [...navDefs];
    if (state.user.role === 'admin') {
      items.push({ id: 'admin', label: 'Admin settings', icon: '★' });
    }
    nav.innerHTML = items
      .map(
        (d) =>
          `<button class="nav-item ${state.section === d.id ? 'active' : ''}" data-section="${d.id}">
             <span class="icon">${d.icon}</span>${esc(d.label)}
           </button>`
      )
      .join('');
    $$('.nav-item', nav).forEach((btn) =>
      btn.addEventListener('click', () => go(btn.dataset.section))
    );
  }

  function renderUserChip() {
    $('#user-chip').innerHTML = `<strong>${esc(state.user.username)}</strong>${esc(state.user.email)} · ${esc(state.user.role)}`;
  }

  const content = $('#content');
  const pageHead = (title, subtitle, actions = '') =>
    `<div class="page-head"><div><h2>${esc(title)}</h2><p>${esc(subtitle)}</p></div><div class="actions">${actions}</div></div>`;

  /* ================= Dashboard ================= */
  async function viewDashboard() {
    const convs = await api('/api/ai/conversation_management?limit=6');
    const access = await api('/api/ai/account_access?limit=5');
    const usage = await api('/api/ai/usage_and_audit_logs?limit=5');
    content.innerHTML = pageHead('Dashboard', 'Your AI workspace at a glance.') + `
      <div class="panel">
        <h3>Recent conversations</h3>
        ${convTable(convs.items)}
      </div>
      <div class="panel">
        <h3>Recent usage</h3>
        ${usageTable(usage.items)}
      </div>
      <div class="panel">
        <h3>Recent account activity</h3>
        ${accessTable(access.items)}
      </div>`;
  }

  /* ================= Account access (AI-01) ================= */
  async function viewAccount() {
    const data = await api('/api/ai/account_access');
    content.innerHTML = pageHead('Account access', 'Sign-in, registration, and access history for your account.') + `
      <div class="panel">
        <h3>Your account</h3>
        <dl class="kv">
          <dt>Username</dt><dd>${esc(state.user.username)}</dd>
          <dt>Email</dt><dd>${esc(state.user.email)}</dd>
          <dt>Role</dt><dd>${esc(state.user.role)}</dd>
          <dt>Status</dt><dd>${esc(state.user.status)}</dd>
        </dl>
      </div>
      <div class="panel">
        <h3>Record access event</h3>
        <form id="access-form" class="inline-form">
          <select name="action">
            <option value="reset">reset</option>
            <option value="login">login</option>
            <option value="logout">logout</option>
            <option value="register">register</option>
          </select>
          <input name="detail" placeholder="Detail (optional)" style="flex:1">
          <button class="btn btn-primary" type="submit">Record</button>
        </form>
      </div>
      <div class="panel"><h3>Access history</h3>${accessTable(data.items)}</div>`;
    $('#access-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await api('/api/ai/account_access', {
          method: 'POST',
          body: Object.fromEntries(new FormData(e.target)),
        });
        toast('Access event recorded', 'success');
        viewAccount();
      } catch (err) {
        toast(err.message, 'error');
      }
    });
  }

  /* ================= Chat (AI-09 / AI-02) ================= */
  async function viewChat() {
    await Promise.all([
      loadChatConversations(),
      loadChatOptions(),
    ]);
    content.innerHTML = `
      <div class="chat-pane">
        <div class="panel chat-list-panel">
          <div class="page-head" style="margin-bottom:8px"><h2 style="font-size:16px">Conversations</h2></div>
          <form id="new-conv" class="inline-form" style="margin-bottom:8px">
            <input name="title" placeholder="New conversation title" style="flex:1; min-width:0">
            <button class="btn btn-primary btn-sm" type="submit">New</button>
          </form>
          <div id="conv-list" class="chat-list"></div>
        </div>
        <div class="chat-thread">
          <div class="chat-thread-header">
            <div>
              <strong id="thread-title">Select a conversation</strong>
              <div class="muted" id="thread-meta"></div>
            </div>
            <div>
              <button id="archive-btn" class="btn btn-sm hidden">Archive</button>
            </div>
          </div>
          <div id="messages" class="chat-messages"><div class="empty">Pick a conversation or create one to start chatting.</div></div>
          <div class="chat-composer">
            <select id="model-select" title="Model configuration"></select>
            <select id="collection-select" title="Retrieval collection (optional)"><option value="">No knowledge collection</option></select>
            <textarea id="prompt-input" placeholder="Send a message... (e.g. what is 12*8?)"></textarea>
            <button id="send-btn" class="btn btn-primary">Send</button>
          </div>
        </div>
      </div>`;

    $('#new-conv').addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        const data = await api('/api/ai/conversation_management', {
          method: 'POST',
          body: { title: e.target.title.value.trim() },
        });
        e.target.reset();
        await loadChatConversations();
        state.chat.activeId = data.id;
        await openConversation(data.id);
        toast('Conversation created', 'success');
      } catch (err) {
        toast(err.message, 'error');
      }
    });

    $('#send-btn').addEventListener('click', sendChat);
    $('#prompt-input').addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendChat();
      }
    });
    $('#archive-btn').addEventListener('click', async () => {
      const id = state.chat.activeId;
      if (!id) return;
      try {
        await api(`/api/ai/conversation_management/${id}`, {
          method: 'PATCH',
          body: { status: 'archived' },
        });
        state.chat.activeId = null;
        await loadChatConversations();
        await openConversation(null);
        toast('Conversation archived', 'success');
      } catch (err) {
        toast(err.message, 'error');
      }
    });
  }

  async function loadChatConversations() {
    const data = await api('/api/ai/conversation_management?status=active');
    state.chat.conversations = data.items;
    renderConvList();
  }

  async function loadChatOptions() {
    const [collections, configs] = await Promise.all([
      api('/api/ai/retrieval_collection'),
      api('/api/ai/model_configuration'),
    ]);
    state.chat.collections = collections.items;
    state.chat.configs = configs.items;
  }

  function renderConvList() {
    const el = $('#conv-list');
    if (!el) return;
    el.innerHTML = state.chat.conversations.length
      ? state.chat.conversations
          .map(
            (c) => `
            <div class="chat-item ${c.id === state.chat.activeId ? 'active' : ''}" data-cid="${c.id}">
              <div class="t">${esc(c.title)}</div>
              <div class="s">${c.message_count} messages · ${fmt(c.updated_at)}</div>
            </div>`
          )
          .join('')
      : `<div class="empty">No active conversations.</div>`;
    $$('.chat-item', el).forEach((item) =>
      item.addEventListener('click', () => openConversation(Number(item.dataset.cid)))
    );
  }

  async function openConversation(id) {
    state.chat.activeId = id;
    renderConvList();
    if (!id) {
      $('#messages').innerHTML = '<div class="empty">Pick a conversation to view messages.</div>';
      $('#thread-title').textContent = 'Select a conversation';
      $('#thread-meta').textContent = '';
      $('#archive-btn').classList.add('hidden');
      return;
    }
    const data = await api(`/api/ai/conversation_management/${id}/messages`);
    state.chat.messages = data.items;
    $('#thread-title').textContent = data.conversation.title;
    $('#thread-meta').textContent = `${data.conversation.status} · created ${fmt(data.conversation.created_at)}`;
    $('#archive-btn').classList.toggle('hidden', data.conversation.status === 'archived');
    renderMessages();
    updateChatSelects();
  }

  function renderMessages() {
    const el = $('#messages');
    el.innerHTML = state.chat.messages.length
      ? state.chat.messages
          .map((m) => {
            const citations = m.citations?.length
              ? `<div class="cite">${m.citations.map((c, i) => `[${i + 1}] ${esc(c.file)} — ${esc(c.snippet)}`).join('<br>')}</div>`
              : '';
            return `<div class="msg ${esc(m.role)}">${esc(m.content)}${citations}</div>`;
          })
          .join('')
      : '<div class="empty">No messages yet. Send the first message below.</div>';
    el.scrollTop = el.scrollHeight;
  }

  function updateChatSelects() {
    const modelSel = $('#model-select');
    modelSel.innerHTML = state.chat.configs.length
      ? state.chat.configs
          .map((c) => `<option value="${c.id}">${esc(c.provider)} / ${esc(c.model)}${c.is_default ? ' (default)' : ''}</option>`)
          .join('')
      : '<option value="">Default model</option>';
    const collSel = $('#collection-select');
    collSel.innerHTML =
      `<option value="">No knowledge collection</option>` +
      state.chat.collections.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
  }

  async function sendChat() {
    const input = $('#prompt-input');
    const prompt = input.value.trim();
    if (!prompt) return;
    const body = {
      prompt,
      conversation_id: state.chat.activeId,
      collection_id: $('#collection-select').value ? Number($('#collection-select').value) : null,
      config_id: $('#model-select').value ? Number($('#model-select').value) : null,
    };
    input.value = '';
    if (state.chat.activeId) {
      state.chat.messages.push({ role: 'user', content: prompt });
      renderMessages();
    }
    try {
      const result = await api('/api/ai/chat_execution', { method: 'POST', body });
      if (!result.ok && result.blocked) {
        state.chat.messages.push({ role: 'system', content: `Blocked: prompt contained the term "${result.blockedTerm}".` });
        renderMessages();
        toast('Prompt blocked by moderation', 'error');
        return;
      }
      const citeBlock = result.citations?.length
        ? result.citations.map((c, i) => `[${i + 1}] ${c.file} — ${c.snippet}`).join('<br>')
        : '';
      if (state.chat.activeId) {
        state.chat.messages.push({ role: 'assistant', content: result.response, citations: result.citations });
      } else {
        state.chat.messages = [
          { role: 'user', content: prompt },
          { role: 'assistant', content: result.response, citations: result.citations },
        ];
      }
      renderMessages();
      if (citeBlock) $('#messages').lastChild?.appendChild;
      if (state.chat.activeId) {
        await loadChatConversations();
      }
      toast(`Reply generated (${result.model})`, 'success');
    } catch (err) {
      state.chat.messages.push({ role: 'system', content: `Error: ${err.message}` });
      renderMessages();
    }
  }

  /* ================= Prompt templates (AI-03) ================= */
  async function viewTemplates() {
    const data = await api('/api/ai/prompt_templates');
    content.innerHTML = pageHead('Prompt templates', 'Your reusable prompts plus admin-published shared templates.') + `
      <div class="panel">
        <h3>Create template</h3>
        <form id="tpl-form" class="inline-form">
          <input name="title" placeholder="Title" required>
          <textarea name="content" placeholder="Template body (use {{PLACEHOLDER}} for variables)" style="flex:1" required></textarea>
          <button class="btn btn-primary" type="submit">Save</button>
        </form>
      </div>
      <div class="panel">
        <h3>Templates</h3>
        <div id="tpl-list"></div>
      </div>`;
    $('#tpl-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await api('/api/ai/prompt_templates', {
          method: 'POST',
          body: { title: e.target.title.value.trim(), content: e.target.content.value },
        });
        toast('Template saved', 'success');
        viewTemplates();
      } catch (err) {
        toast(err.message, 'error');
      }
    });
    const list = $('#tpl-list');
    list.innerHTML = data.items.length
      ? data.items
          .map(
            (t) => `
            <div class="panel" style="margin-bottom:10px">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
                <div>
                  <strong>${esc(t.title)}</strong>
                  <span class="badge ${t.is_public ? 'green' : 'muted'}">${t.scope === 'shared' && t.is_public ? 'shared' : 'private'}</span>
                  ${t.user_id === state.user.id ? '' : '<span class="badge amber">published</span>'}
                  <pre style="white-space:pre-wrap;margin:8px 0 0">${esc(t.content)}</pre>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0">
                  ${t.user_id === state.user.id ? `<button class="btn btn-sm" data-act="edit" data-id="${t.id}">Edit</button>` : ''}
                  ${state.user.role === 'admin' ? `<button class="btn btn-sm" data-act="publish" data-id="${t.id}">${t.is_public ? 'Unpublish' : 'Publish'}</button>` : ''}
                </div>
              </div>
            </div>`
          )
          .join('')
      : '<div class="empty">No templates yet.</div>';
    $$('button[data-act]', list).forEach((btn) =>
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const body =
          btn.dataset.act === 'publish'
            ? (() => {
                const t = data.items.find((x) => x.id === Number(id));
                return { scope: t.is_public ? 'private' : 'shared', is_public: !t.is_public };
              })()
            : null;
        if (btn.dataset.act === 'edit') {
          const t = data.items.find((x) => x.id === Number(id));
          const title = prompt('Title', t.title);
          const content = prompt('Content', t.content);
          if (title === null || content === null) return;
          await api(`/api/ai/prompt_templates/${id}`, { method: 'PATCH', body: { title, content } });
        } else {
          await api(`/api/ai/prompt_templates/${id}`, { method: 'PATCH', body });
        }
        toast('Template updated', 'success');
        viewTemplates();
      })
    );
  }

  /* ================= Model configuration (AI-04) ================= */
  async function viewModels() {
    const data = await api('/api/ai/model_configuration');
    content.innerHTML = pageHead('Model configuration', 'Model profile, temperature, context length, and safety mode.') + `
      <div class="panel">
        <h3>Add configuration</h3>
        <form id="model-form" class="inline-form">
          <div class="field-row">
            <label>Provider<input name="provider" value="openai" required></label>
            <label>Model<input name="model" value="gpt-4o-mini" required></label>
            <label>Temperature<input name="temperature" type="number" step="0.1" min="0" max="2" value="0.7"></label>
            <label>Context length<input name="context_length" type="number" min="256" value="4096"></label>
            <label>Safety mode<select name="safety_mode"><option value="balanced">balanced</option><option value="strict">strict</option><option value="off">off</option></select></label>
            <label style="flex-direction:row;align-items:center;gap:8px;color:var(--text)"><input name="is_default" type="checkbox" checked> Default</label>
          </div>
          <button class="btn btn-primary" type="submit">Add</button>
        </form>
        <p class="muted">Models enabled by admin: ${esc((data.model_access || []).join(', '))}</p>
      </div>
      <div class="panel"><h3>Configurations</h3>${modelTable(data.items)}</div>`;
    $('#model-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const f = new FormData(e.target);
      try {
        await api('/api/ai/model_configuration', {
          method: 'POST',
          body: {
            provider: f.get('provider'),
            model: f.get('model'),
            temperature: Number(f.get('temperature')),
            context_length: Number(f.get('context_length')),
            safety_mode: f.get('safety_mode'),
            is_default: f.get('is_default') === 'on',
          },
        });
        toast('Configuration added', 'success');
        viewModels();
      } catch (err) {
        toast(err.message, 'error');
      }
    });
  }

  /* ================= Knowledge files (AI-05) ================= */
  async function viewKnowledge() {
    const data = await api('/api/ai/knowledge_file_upload');
    content.innerHTML = pageHead('Knowledge file upload', 'Upload documents to attach to conversations or retrieval collections.') + `
      <div class="panel">
        <h3>Upload a file</h3>
        <form id="file-form" class="inline-form">
          <input type="file" name="file" required>
          <input name="description" placeholder="Description (optional)" style="flex:1">
          <button class="btn btn-primary" type="submit">Upload</button>
        </form>
        <p class="muted">Max size: ${(data.max_upload_size / 1024 / 1024).toFixed(1)} MB · Allowed types: text/plain, text/markdown, text/csv, application/json</p>
      </div>
      <div class="panel"><h3>Uploaded files</h3>${fileTable(data.items)}</div>`;
    $('#file-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      try {
        const result = await api('/api/ai/knowledge_file_upload', { method: 'POST', body: fd });
        toast(`Uploaded ${result.original_name}`, 'success');
        viewKnowledge();
      } catch (err) {
        toast(err.message, 'error');
      }
    });
    $$('[data-act="edit-desc"]', content).forEach((btn) =>
      btn.addEventListener('click', async () => {
        const desc = prompt('Description');
        if (desc === null) return;
        await api(`/api/ai/knowledge_file_upload/${btn.dataset.id}`, { method: 'PATCH', body: { description: desc } });
        toast('Description updated', 'success');
        viewKnowledge();
      })
    );
  }

  /* ================= Retrieval collection (AI-06) ================= */
  async function viewRetrieval() {
    const data = await api('/api/ai/retrieval_collection');
    const files = await api('/api/ai/knowledge_file_upload');
    content.innerHTML = pageHead('Retrieval collection', 'Create collections and search your uploaded knowledge.') + `
      <div class="panel">
        <h3>Search knowledge</h3>
        <form id="search-form" class="inline-form">
          <input name="search" placeholder="Search your knowledge files..." style="flex:1" required>
          <button class="btn" type="submit">Search</button>
        </form>
        <div id="search-results"></div>
      </div>
      <div class="panel">
        <h3>New collection</h3>
        <form id="coll-form" class="inline-form">
          <input name="name" placeholder="Collection name" required>
          <input name="description" placeholder="Description" style="flex:1">
          <select name="file_ids" multiple style="min-width:220px" title="Attach knowledge files">
            ${files.items.map((f) => `<option value="${f.id}">${esc(f.original_name)}</option>`).join('')}
          </select>
          <button class="btn btn-primary" type="submit">Create</button>
        </form>
      </div>
      <div class="panel"><h3>Collections</h3>${collectionTable(data.items)}</div>`;

    $('#search-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const q = e.target.search.value.trim();
      try {
        const results = await api(`/api/ai/retrieval_collection?search=${encodeURIComponent(q)}`);
        $('#search-results').innerHTML =
          `<h4 style="margin:14px 0 8px">${results.items.length} result(s) for "${esc(q)}"</h4>` +
          (results.items.length
            ? results.items
                .map(
                  (r, i) =>
                    `<div class="panel" style="margin-bottom:8px"><strong>[${i + 1}] ${esc(r.file)}</strong><div class="muted">${esc(r.snippet)}</div></div>`
                )
                .join('')
            : '<div class="empty">No matches found.</div>');
      } catch (err) {
        toast(err.message, 'error');
      }
    });

    $('#coll-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const f = new FormData(e.target);
      try {
        await api('/api/ai/retrieval_collection', {
          method: 'POST',
          body: {
            name: f.get('name'),
            description: f.get('description'),
            file_ids: [...f.getAll('file_ids')].map(Number),
          },
        });
        toast('Collection created', 'success');
        viewRetrieval();
      } catch (err) {
        toast(err.message, 'error');
      }
    });
    bindCollectionClicks(data.items);
  }

  /* ================= Tools (AI-07) ================= */
  async function viewTools() {
    const data = await api('/api/ai/tool_plugin_registry');
    const adminForm = state.user.role === 'admin'
      ? `<div class="panel">
          <h3>Register tool (admin)</h3>
          <form id="tool-form" class="inline-form">
            <input name="name" placeholder="Tool name (e.g. web_search)" required>
            <input name="description" placeholder="Description" style="flex:1">
            <input name="config" placeholder='Config JSON, e.g. {"max_results": 3}' style="flex:1">
            <button class="btn btn-primary" type="submit">Register</button>
          </form>
        </div>`
      : '';
    content.innerHTML = pageHead('Tool / plugin registry', 'Admins configure tools; users enable allowed tools for their workspace.') + adminForm + `
      <div class="panel"><h3>Tools</h3>
        <table><thead><tr><th>Tool</th><th>Description</th><th>Status</th><th>Enabled for me</th></tr></thead>
        <tbody>${data.items
          .map(
            (t) => `
            <tr>
              <td><strong>${esc(t.name)}</strong>${state.user.role === 'admin' ? `<div class="muted">config: <code>${esc(t.config)}</code></div>` : ''}</td>
              <td>${esc(t.description)}</td>
              <td><span class="badge ${t.enabled ? 'green' : 'red'}">${t.enabled ? 'enabled' : 'disabled'}</span></td>
              <td>
                <label class="switch">
                  <input type="checkbox" data-tool-id="${t.id}" ${t.user_enabled ? 'checked' : ''} ${t.enabled ? '' : 'disabled'}>
                </label>
              </td>
            </tr>`
          )
          .join('')}
        </tbody></table>
      </div>`;
    $('#tool-form')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await api('/api/ai/tool_plugin_registry', {
          method: 'POST',
          body: { name: e.target.name.value.trim(), description: e.target.description.value, config: e.target.config.value },
        });
        toast('Tool registered', 'success');
        viewTools();
      } catch (err) {
        toast(err.message, 'error');
      }
    });
    $$('input[data-tool-id]', content).forEach((cb) =>
      cb.addEventListener('change', async () => {
        try {
          await api(`/api/ai/tool_plugin_registry/${cb.dataset.toolId}`, {
            method: 'PATCH',
            body: { user_enabled: cb.checked },
          });
          toast(`${cb.checked ? 'Enabled' : 'Disabled'} tool`, 'success');
        } catch (err) {
          cb.checked = !cb.checked;
          toast(err.message, 'error');
        }
      })
    );
  }

  /* ================= API keys (AI-08) ================= */
  async function viewKeys() {
    const data = await api('/api/ai/api_key_management');
    content.innerHTML = pageHead('API key management', 'Store masked provider keys and rotate or revoke them.') + `
      <div class="panel">
        <h3>Add provider key</h3>
        <form id="key-form" class="inline-form">
          <input name="provider" placeholder="provider (e.g. openai)" required>
          <input name="key" placeholder="sk-..." style="flex:1" required>
          <button class="btn btn-primary" type="submit">Store</button>
        </form>
        <p class="muted">Keys are stored masked; the raw value is never returned by the API.</p>
      </div>
      <div class="panel"><h3>Keys</h3>
        <table><thead><tr><th>Provider</th><th>Key</th><th>Status</th><th>Last used</th><th></th></tr></thead>
        <tbody>${data.items
          .map(
            (k) => `
            <tr>
              <td>${esc(k.provider)}</td>
              <td><code>${esc(k.masked_key)}</code></td>
              <td><span class="badge ${k.status === 'active' ? 'green' : 'red'}">${k.status}</span></td>
              <td>${fmt(k.last_used_at)}</td>
              <td>
                <button class="btn btn-sm" data-act="rotate" data-id="${k.id}" ${k.status !== 'active' ? 'disabled' : ''}>Rotate</button>
                <button class="btn btn-sm btn-danger" data-act="revoke" data-id="${k.id}" ${k.status !== 'active' ? 'disabled' : ''}>Revoke</button>
              </td>
            </tr>`
          )
          .join('')}
        </tbody></table>
      </div>`;
    $('#key-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await api('/api/ai/api_key_management', {
          method: 'POST',
          body: { provider: e.target.provider.value.trim(), key: e.target.key.value },
        });
        e.target.reset();
        toast('Key stored (masked)', 'success');
        viewKeys();
      } catch (err) {
        toast(err.message, 'error');
      }
    });
    $$('[data-act]', content).forEach((btn) =>
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        if (btn.dataset.act === 'rotate') {
          const key = prompt('Enter the new key value to rotate to');
          if (!key) return;
          await api(`/api/ai/api_key_management/${id}`, { method: 'PATCH', body: { action: 'rotate', key } });
        } else {
          await api(`/api/ai/api_key_management/${id}`, { method: 'PATCH', body: { action: 'revoke' } });
        }
        toast('Key updated', 'success');
        viewKeys();
      })
    );
  }

  /* ================= Share conversation (AI-10) ================= */
  async function viewShares() {
    const [shares, convs] = await Promise.all([
      api('/api/ai/share_conversation'),
      api('/api/ai/conversation_management?status=active'),
    ]);
    content.innerHTML = pageHead('Share conversation', 'Create limited public share links for selected conversations.') + `
      <div class="panel">
        <h3>Create share link</h3>
        <form id="share-form" class="inline-form">
          <select name="conversation_id" required>
            ${convs.items.map((c) => `<option value="${c.id}">${esc(c.title)}</option>`).join('')}
          </select>
          <input name="expires_at" type="datetime-local" title="Expiry (optional)">
          <button class="btn btn-primary" type="submit">Create link</button>
        </form>
      </div>
      <div class="panel"><h3>Share links</h3>
        <table><thead><tr><th>Conversation</th><th>Link</th><th>Status</th><th>Created</th><th></th></tr></thead>
        <tbody>${shares.items
          .map(
            (s) => `
            <tr>
              <td>${esc(s.conversation_title)}</td>
              <td><code>/share/${esc(s.token)}</code></td>
              <td><span class="badge ${s.revoked ? 'red' : 'green'}">${s.revoked ? 'revoked' : s.expires_at && new Date(s.expires_at) < new Date() ? 'expired' : 'active'}</span></td>
              <td>${fmt(s.created_at)}</td>
              <td>
                <button class="btn btn-sm" data-act="open" data-token="${s.token}" ${s.revoked ? 'disabled' : ''}>Open</button>
                <button class="btn btn-sm btn-danger" data-act="revoke" data-id="${s.id}" ${s.revoked ? 'disabled' : ''}>Revoke</button>
              </td>
            </tr>`
          )
          .join('')}
        </tbody></table>
      </div>`;
    $('#share-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const f = new FormData(e.target);
      try {
        const result = await api('/api/ai/share_conversation', {
          method: 'POST',
          body: {
            conversation_id: Number(f.get('conversation_id')),
            expires_at: f.get('expires_at') ? new Date(f.get('expires_at')).toISOString() : null,
          },
        });
        toast(`Share link created: /share/${result.token}`, 'success');
        viewShares();
      } catch (err) {
        toast(err.message, 'error');
      }
    });
    $$('[data-act]', content).forEach((btn) =>
      btn.addEventListener('click', async () => {
        if (btn.dataset.act === 'open') {
          window.open(`/share/${btn.dataset.token}`, '_blank');
          return;
        }
        await api(`/api/ai/share_conversation/${btn.dataset.id}`, { method: 'PATCH', body: { revoked: true } });
        toast('Share revoked', 'success');
        viewShares();
      })
    );
  }

  /* ================= Usage & audit logs (AI-11) ================= */
  async function viewLogs() {
    content.innerHTML = pageHead('Usage and audit logs', 'Your usage; administrators review system activity.') + `
      <div class="tabs">
        <button class="tab-btn active" data-type="usage">Usage</button>
        <button class="tab-btn" data-type="audit">Audit</button>
      </div>
      <div class="panel" id="logs-body"><div class="empty">Loading...</div></div>`;
    $$('.tab-btn', content).forEach((btn) =>
      btn.addEventListener('click', () => {
        $$('.tab-btn', content).forEach((b) => b.classList.toggle('active', b === btn));
        loadLogs(btn.dataset.type);
      })
    );
    loadLogs('usage');
  }

  async function loadLogs(type) {
    const body = $('#logs-body');
    try {
      const data = await api(`/api/ai/usage_and_audit_logs?type=${type}`);
      if (type === 'usage') {
        const summary = (data.summary || [])
          .map((s) => `<span class="badge muted">${esc(s.module)}: ${s.calls} calls, ${s.tokens} tokens</span>`)
          .join(' ');
        body.innerHTML =
          `<div style="margin-bottom:10px;display:flex;gap:6px;flex-wrap:wrap">${summary || '<span class="muted">No usage yet</span>'}</div>` +
          (data.items.length ? usageTable(data.items) : '<div class="empty">No usage logs match the filters.</div>');
      } else {
        body.innerHTML = data.items.length ? auditTable(data.items) : '<div class="empty">No audit events match the filters.</div>';
      }
    } catch (err) {
      body.innerHTML = `<div class="error-box">${esc(err.message)}</div>`;
    }
  }

  /* ================= Admin (AI-12) ================= */
  async function viewAdmin() {
    const data = await api('/api/ai/admin_moderation_and_settings');
    content.innerHTML = pageHead('Admin moderation and settings', 'Manage users, model access, blocked terms, and system defaults.') + `
      <div class="panel">
        <h3>System settings</h3>
        <table><thead><tr><th>Setting</th><th>Value</th><th></th></tr></thead>
        <tbody>
          <tr><td>default_model</td><td><code>${esc(data.settings.default_model)}</code></td><td><button class="btn btn-sm" data-key="default_model" data-act="setting">Edit</button></td></tr>
          <tr><td>default_provider</td><td><code>${esc(data.settings.default_provider)}</code></td><td><button class="btn btn-sm" data-key="default_provider" data-act="setting">Edit</button></td></tr>
          <tr><td>allow_registration</td><td>${String(data.settings.allow_registration)}</td><td><button class="btn btn-sm" data-key="allow_registration" data-act="toggle">Toggle</button></td></tr>
          <tr><td>max_upload_size</td><td><code>${data.settings.max_upload_size}</code> bytes</td><td><button class="btn btn-sm" data-key="max_upload_size" data-act="setting">Edit</button></td></tr>
        </tbody></table>
      </div>
      <div class="panel">
        <h3>Blocked terms</h3>
        <form id="terms-form" class="inline-form">
          <input name="term" placeholder="Add blocked term" style="flex:1">
          <button class="btn" type="submit">Add</button>
        </form>
        <div id="terms-list" style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap">
          ${data.blocked_terms.map((t, i) => `<span class="badge red">${esc(t)} <a href="#" data-term-index="${i}" data-term="${esc(t)}" style="color:inherit">✕</a></span>`).join('')}
        </div>
      </div>
      <div class="panel">
        <h3>Model access</h3>
        <form id="model-access-form" class="inline-form">
          <input name="model" placeholder="Add model name" style="flex:1">
          <button class="btn" type="submit">Add</button>
        </form>
        <div id="ma-list" style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap">
          ${data.model_access.map((m) => `<span class="badge green">${esc(m)} <a href="#" data-model="${esc(m)}" style="color:inherit">✕</a></span>`).join('')}
        </div>
      </div>
      <div class="panel"><h3>Users</h3>${userTable(data.users)}</div>`;

    $$('[data-act="setting"]', content).forEach((btn) =>
      btn.addEventListener('click', async () => {
        const key = btn.dataset.key;
        const current = data.settings[key];
        const value = prompt(`New value for ${key}`, current);
        if (value === null) return;
        await api(`/api/ai/admin_moderation_and_settings/0`, {
          method: 'PATCH',
          body: { action: 'update_setting', setting_key: key, setting_value: key === 'max_upload_size' ? Number(value) : value },
        });
        toast('Setting updated', 'success');
        viewAdmin();
      })
    );
    $$('[data-act="toggle"]', content).forEach((btn) =>
      btn.addEventListener('click', async () => {
        const key = btn.dataset.key;
        await api(`/api/ai/admin_moderation_and_settings/0`, {
          method: 'PATCH',
          body: { action: 'update_setting', setting_key: key, setting_value: !data.settings[key] },
        });
        toast('Setting toggled', 'success');
        viewAdmin();
      })
    );
    $('#terms-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const term = e.target.term.value.trim();
      if (!term) return;
      await api(`/api/ai/admin_moderation_and_settings/0`, {
        method: 'PATCH',
        body: { action: 'update_blocked_terms', terms: [...data.blocked_terms, term] },
      });
      toast('Term added', 'success');
      viewAdmin();
    });
    $$('#terms-list a[data-term]').forEach((a) =>
      a.addEventListener('click', async (ev) => {
        ev.preventDefault();
        const term = a.dataset.term;
        await api(`/api/ai/admin_moderation_and_settings/0`, {
          method: 'PATCH',
          body: { action: 'update_blocked_terms', terms: data.blocked_terms.filter((t) => t !== term) },
        });
        viewAdmin();
      })
    );
    $('#model-access-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const model = e.target.model.value.trim();
      if (!model) return;
      await api(`/api/ai/admin_moderation_and_settings/0`, {
        method: 'PATCH',
        body: { action: 'update_model_access', terms: [...data.model_access, model] },
      });
      toast('Model added', 'success');
      viewAdmin();
    });
    $$('#ma-list a[data-model]').forEach((a) =>
      a.addEventListener('click', async (ev) => {
        ev.preventDefault();
        const model = a.dataset.model;
        await api(`/api/ai/admin_moderation_and_settings/0`, {
          method: 'PATCH',
          body: { action: 'update_model_access', terms: data.model_access.filter((m) => m !== model) },
        });
        viewAdmin();
      })
    );
    $$('[data-act="suspend"]').forEach((btn) =>
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        await api(`/api/ai/admin_moderation_and_settings/${id}`, {
          method: 'PATCH',
          body: { action: 'user_status', status: btn.dataset.status },
        });
        toast('User status updated', 'success');
        viewAdmin();
      })
    );
  }

  /* ================= tables ================= */
  function convTable(items) {
    return items.length
      ? `<table><thead><tr><th>Title</th><th>Status</th><th>Messages</th><th>Updated</th></tr></thead><tbody>
         ${items.map((c) => `<tr><td>${esc(c.title)}</td><td><span class="badge ${c.status === 'active' ? 'green' : 'muted'}">${c.status}</span></td><td>${c.message_count}</td><td>${fmt(c.updated_at)}</td></tr>`).join('')}
         </tbody></table>`
      : '<div class="empty">No conversations yet.</div>';
  }
  function usageTable(items) {
    return items.length
      ? `<table><thead><tr><th>Module</th><th>Action</th><th>Tokens</th><th>When</th></tr></thead><tbody>
         ${items.map((l) => `<tr><td>${esc(l.module)}</td><td>${esc(l.action)}</td><td>${l.tokens_used}</td><td>${fmt(l.created_at)}</td></tr>`).join('')}
         </tbody></table>`
      : '<div class="empty">No usage records.</div>';
  }
  function accessTable(items) {
    return items.length
      ? `<table><thead><tr><th>User</th><th>Action</th><th>Detail</th><th>When</th></tr></thead><tbody>
         ${items.map((a) => `<tr><td>${esc(a.username)}</td><td>${esc(a.action)}</td><td class="muted">${esc(a.detail || '')}</td><td>${fmt(a.created_at)}</td></tr>`).join('')}
         </tbody></table>`
      : '<div class="empty">No access events.</div>';
  }
  function auditTable(items) {
    return items.length
      ? `<table><thead><tr><th>User</th><th>Action</th><th>Module</th><th>Detail</th><th>When</th></tr></thead><tbody>
         ${items.map((a) => `<tr><td>${esc(a.username || 'system')}</td><td>${esc(a.action)}</td><td>${esc(a.module)}</td><td class="muted"><code>${esc(JSON.stringify(a.detail))}</code></td><td>${fmt(a.created_at)}</td></tr>`).join('')}
         </tbody></table>`
      : '<div class="empty">No audit events.</div>';
  }
  function fileTable(items) {
    return items.length
      ? `<table><thead><tr><th>Name</th><th>Size</th><th>Type</th><th>Description</th><th>Uploaded</th><th></th></tr></thead><tbody>
         ${items.map((f) => `<tr><td>${esc(f.original_name)}</td><td>${f.size} B</td><td>${esc(f.mime_type)}</td><td class="muted">${esc(f.description || '')}</td><td>${fmt(f.created_at)}</td><td><button class="btn btn-sm" data-act="edit-desc" data-id="${f.id}">Edit</button></td></tr>`).join('')}
         </tbody></table>`
      : '<div class="empty">No uploaded files.</div>';
  }
  function modelTable(items) {
    return items.length
      ? `<table><thead><tr><th>Provider</th><th>Model</th><th>Temp</th><th>Context</th><th>Safety</th><th>Default</th><th></th></tr></thead><tbody>
         ${items.map((c) => `<tr>
           <td>${esc(c.provider)}</td><td>${esc(c.model)}</td><td>${c.temperature}</td><td>${c.context_length}</td>
           <td>${esc(c.safety_mode)}</td><td>${c.is_default ? '<span class="badge green">default</span>' : ''}</td>
           <td>${c.is_default ? '' : `<button class="btn btn-sm" data-act="set-default" data-id="${c.id}">Set default</button>`}</td>
         </tr>`).join('')}
         </tbody></table>`
      : '<div class="empty">No model configurations yet.</div>';
  }
  function collectionTable(items) {
    return items.length
      ? `<table><thead><tr><th>Name</th><th>Description</th><th>Files</th><th>Created</th><th></th></tr></thead><tbody>
         ${items.map((c) => `<tr>
           <td>${esc(c.name)}</td><td class="muted">${esc(c.description || '')}</td><td>${c.file_count}</td><td>${fmt(c.created_at)}</td>
           <td><button class="btn btn-sm" data-act="open-coll" data-id="${c.id}">Open</button></td>
         </tr>`).join('')}
         </tbody></table>`
      : '<div class="empty">No collections yet.</div>';
  }
  function userTable(users) {
    return `<table><thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>
      ${users
        .map(
          (u) => `
          <tr>
            <td>${esc(u.username)}</td><td>${esc(u.email)}</td><td>${esc(u.role)}</td>
            <td><span class="badge ${u.status === 'active' ? 'green' : 'red'}">${u.status}</span></td>
            <td>
              ${u.id === state.user.id ? '<span class="muted">you</span>' : u.status === 'active' ? `<button class="btn btn-sm btn-danger" data-act="suspend" data-id="${u.id}" data-status="suspended">Suspend</button>` : `<button class="btn btn-sm" data-act="suspend" data-id="${u.id}" data-status="active">Activate</button>`}
            </td>
          </tr>`
        )
        .join('')}
      </tbody></table>`;
  }

  function bindCollectionClicks(items) {
    $$('[data-act="open-coll"]', content).forEach((btn) =>
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const detail = await api(`/api/ai/retrieval_collection/${id}`);
        const files = await api('/api/ai/knowledge_file_upload');
        const openIds = detail.files.map((f) => f.id);
        const addForm = `
          <form id="add-files-form" class="inline-form" style="margin-top:10px">
            <select name="file_ids" multiple style="min-width:220px">
              ${files.items.filter((f) => !openIds.includes(f.id)).map((f) => `<option value="${f.id}">${esc(f.original_name)}</option>`).join('')}
            </select>
            <button class="btn btn-sm" type="submit">Add files</button>
          </form>`;
        const modal = document.createElement('div');
        modal.className = 'panel';
        modal.innerHTML = `
          <div style="display:flex;justify-content:space-between;align-items:center">
            <h3>${esc(detail.collection.name)}</h3>
            <button class="btn btn-sm btn-ghost" data-close="1">✕ Close</button>
          </div>
          <dl class="kv"><dt>Description</dt><dd>${esc(detail.collection.description || '—')}</dd></dl>
          <h4>Files</h4>
          ${detail.files.length ? `<table><thead><tr><th>File</th><th>Type</th><th></th></tr></thead><tbody>
            ${detail.files.map((f) => `<tr><td>${esc(f.original_name)}</td><td>${esc(f.mime_type)}</td><td><button class="btn btn-sm" data-remove="${f.id}">Remove</button></td></tr>`).join('')}
          </tbody></table>` : '<div class="empty">No files in this collection.</div>'}
          ${addForm}`;
        $('#content').prepend(modal);
        modal.querySelector('[data-close]').addEventListener('click', () => modal.remove());
        $('#add-files-form').addEventListener('submit', async (e) => {
          e.preventDefault();
          const fileIds = [...new FormData(e.target).getAll('file_ids')].map(Number);
          await api(`/api/ai/retrieval_collection/${id}`, { method: 'PATCH', body: { add_files: fileIds } });
          toast('Files added', 'success');
          viewRetrieval();
        });
        $$('[data-remove]', modal).forEach((rm) =>
          rm.addEventListener('click', async () => {
            await api(`/api/ai/retrieval_collection/${id}`, { method: 'PATCH', body: { remove_files: [Number(rm.dataset.remove)] } });
            toast('File removed', 'success');
            viewRetrieval();
          })
        );
      })
    );
  }

  /* ================= navigation ================= */
  async function go(section) {
    state.section = section;
    renderNav();
    window.history.replaceState(null, '', `#${section}`);
    content.innerHTML = '<div class="empty">Loading...</div>';
    try {
      switch (section) {
        case 'dashboard': return await viewDashboard();
        case 'chat': return await viewChat();
        case 'templates': return await viewTemplates();
        case 'models': return await viewModels();
        case 'knowledge': return await viewKnowledge();
        case 'retrieval': return await viewRetrieval();
        case 'tools': return await viewTools();
        case 'keys': return await viewKeys();
        case 'shares': return await viewShares();
        case 'logs': return await viewLogs();
        case 'account': return await viewAccount();
        case 'admin':
          if (state.user.role !== 'admin') throw new Error('Administrator access required');
          return await viewAdmin();
        default: return await viewDashboard();
      }
    } catch (err) {
      content.innerHTML = `<div class="error-box">${esc(err.message)}</div>`;
    }
  }

  function connectWs() {
    const proto = location.protocol === 'https:' ? 'wss' : 'ws';
    let ws;
    try {
      ws = new WebSocket(`${proto}://${location.host}/ws`);
    } catch {
      return;
    }
    ws.addEventListener('message', (event) => {
      try {
        const msg = JSON.parse(event.data);
        if (msg.type === 'ws:connected') {
          toast('Realtime channel connected', 'info');
        }
        if (msg.type === 'chat:execution' && state.section === 'chat' && msg.data.user_id === state.user.id) {
          toast('Realtime: new assistant reply generated', 'success');
          if (state.chat.activeId) {
            openConversation(state.chat.activeId).catch(() => {});
          }
        }
      } catch {
        /* ignore malformed frames */
      }
    });
    ws.addEventListener('close', () => setTimeout(connectWs, 3000));
  }

  $('#signout').addEventListener('click', async () => {
    try {
      await api('/api/auth/logout', { method: 'POST' });
    } finally {
      window.location.replace('/auth');
    }
  });

  (async function init() {
    try {
      const data = await api('/api/auth/me');
      state.user = data.user;
    } catch {
      window.location.replace('/auth');
      return;
    }
    $('#boot').classList.add('hidden');
    $('#app').classList.remove('hidden');
    renderUserChip();
    const initial = (window.location.hash || '#dashboard').slice(1);
    await go(navDefs.some((d) => d.id === initial) || initial === 'admin' ? initial : 'dashboard');
    connectWs();
  })();
})();
