/* Real-time chat client: workspaces, channels, topics, messages, presence,
   typing indicators, reconnects, duplicate-send handling, search and the
   frontend API states (loading / empty / error). */

(function () {
  'use strict';

  const boot = window.CHAT_BOOT || {};
  const CURRENT_USER = boot.currentUser || { id: 0, username: '?' };
  let state = {
    ws: null,
    connected: false,
    reconnectAttempts: 0,
    workspaces: [],
    channelsByWs: {},
    myRole: {},
    currentWorkspace: boot.workspaceSlug || null,
    currentChannel: boot.channelId ? Number(boot.channelId) : null,
    currentTopic: '__general__',
    online: new Set(),
    typingTimers: {},
    pendingClientIds: new Set(),
    loading: false,
    hasMore: true,
  };

  const $ = (id) => document.getElementById(id);
  const messageList = $('message-list');
  const emptyState = $('empty-state');
  const connStatus = $('conn-status');

  /* ---------------------------------------------------------- connection */

  function connect() {
    const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
    const url = protocol + '//' + location.host + '/ws/chat';
    setConnStatus('connecting…', '');
    let ws;
    try {
      ws = new WebSocket(url);
    } catch (_) {
      scheduleReconnect();
      return;
    }
    state.ws = ws;

    ws.onopen = function () {
      state.connected = true;
      state.reconnectAttempts = 0;
      setConnStatus('online', 'online');
      if (state.reconnectAttempts > 0) {
        reportFrontend('reconnect');
      }
      subscribe();
    };

    ws.onmessage = function (event) {
      let msg;
      try {
        msg = JSON.parse(event.data);
      } catch (_) {
        return;
      }
      handleServerMessage(msg);
    };

    ws.onclose = function () {
      state.connected = false;
      state.ws = null;
      setConnStatus('offline — reconnecting…', 'offline');
      scheduleReconnect();
    };

    ws.onerror = function () {
      try { ws.close(); } catch (_) { /* noop */ }
    };
  }

  function scheduleReconnect() {
    const delay = Math.min(1000 * Math.pow(2, state.reconnectAttempts), 15000);
    state.reconnectAttempts += 1;
    setTimeout(connect, delay);
  }

  function sendSocket(payload) {
    if (!state.ws || state.ws.readyState !== WebSocket.OPEN) {
      return false;
    }
    try {
      state.ws.send(JSON.stringify(payload));
      return true;
    } catch (_) {
      return false;
    }
  }

  function subscribe() {
    sendSocket({
      type: 'subscribe',
      workspace_slug: state.currentWorkspace,
      channels: Object.keys(state.channelsByWs[state.currentWorkspace] || {}).map(Number),
      threads: [],
    });
    sendSocket({ type: 'presence' });
  }

  function setConnStatus(text, cls) {
    connStatus.textContent = text;
    connStatus.className = 'conn-status' + (cls ? ' ' + cls : '');
  }

  /* ---------------------------------------------------------- data load */

  async function loadWorkspaces() {
    const { payload } = await apiFetch('/api/chat/workspaces_and_channels');
    if (!payload || !payload.ok) return;
    state.workspaces = payload.data.workspaces || [];
    state.channelsByWs = {};
    state.myRole = {};
    for (const entry of state.workspaces) {
      state.channelsByWs[entry.workspace.slug] = {};
      state.myRole[entry.workspace.slug] = entry.role;
      for (const channel of entry.channels) {
        state.channelsByWs[entry.workspace.slug][channel.id] = channel;
      }
    }
    if (!state.currentWorkspace && state.workspaces.length) {
      state.currentWorkspace = state.workspaces[0].workspace.slug;
      const channels = state.channelsByWs[state.currentWorkspace];
      const ids = Object.keys(channels);
      if (!state.currentChannel && ids.length) {
        state.currentChannel = Number(ids[0]);
      }
    }
    renderWorkspaces();
    renderChannels();
    if (state.currentChannel) {
      await loadMessages(true);
    } else {
      renderEmpty('Select a channel to begin.');
    }
    subscribe();
  }

  function renderWorkspaces() {
    const container = $('workspace-list');
    container.innerHTML = '';
    if (!state.workspaces.length) {
      container.appendChild(el('div', 'muted small', 'No workspaces. Create one on the Workspaces page.'));
      return;
    }
    for (const entry of state.workspaces) {
      const item = el('div', 'nav-item' + (entry.workspace.slug === state.currentWorkspace ? ' active' : ''),
        entry.workspace.name + ' (' + entry.role + ')');
      item.addEventListener('click', () => switchWorkspace(entry.workspace.slug));
      container.appendChild(item);
    }
  }

  function switchWorkspace(slug) {
    state.currentWorkspace = slug;
    const channels = state.channelsByWs[slug] || {};
    const ids = Object.keys(channels);
    state.currentChannel = ids.length ? Number(ids[0]) : null;
    renderWorkspaces();
    renderChannels();
    subscribe();
    if (state.currentChannel) loadMessages(true);
    else renderEmpty('Select a channel to begin.');
  }

  function renderChannels() {
    const container = $('channel-list');
    container.innerHTML = '';
    const channels = state.channelsByWs[state.currentWorkspace] || {};
    const ids = Object.keys(channels).sort((a, b) => channels[a].name.localeCompare(channels[b].name));
    if (!ids.length) {
      container.appendChild(el('div', 'muted small', 'No channels.'));
      return;
    }
    for (const id of ids) {
      const channel = channels[id];
      const item = el('div', 'nav-item' + (Number(id) === state.currentChannel ? ' active' : ''),
        '#' + channel.name + (channel.visibility === 'private' ? ' 🔒' : '') + (channel.archived ? ' (archived)' : ''));
      item.addEventListener('click', () => switchChannel(Number(id)));
      container.appendChild(item);
    }
  }

  function switchChannel(channelId) {
    state.currentChannel = channelId;
    state.currentTopic = '__general__';
    state.hasMore = true;
    renderChannels();
    $('chat-title').textContent = '#' + (state.channelsByWs[state.currentWorkspace][channelId] || {}).name || 'Channel';
    $('chat-subtitle').textContent = (state.channelsByWs[state.currentWorkspace][channelId] || {}).description || '';
    hideSearch();
    loadMessages(true);
    if (state.ws && state.ws.readyState === WebSocket.OPEN) {
      sendSocket({ type: 'subscribe', workspace_slug: state.currentWorkspace, channels: Object.keys(state.channelsByWs[state.currentWorkspace] || {}).map(Number), threads: [] });
    }
  }

  async function loadMessages(reset) {
    if (!state.currentChannel) return;
    const channel = state.channelsByWs[state.currentWorkspace][state.currentChannel];
    if (!channel || channel.archived) {
      renderEmpty('This channel is archived.');
      return;
    }
    const beforeId = reset ? undefined : firstMessageId();
    setLoading();
    const query = new URLSearchParams({
      slug: state.currentWorkspace,
      channel_id: String(state.currentChannel),
    });
    if (beforeId) query.set('before_id', String(beforeId));
    if (state.currentTopic !== '__general__') query.set('topic_id', state.currentTopic);
    const { payload } = await apiFetch('/api/chat/real_time_messaging?' + query.toString());
    clearLoading();
    if (!payload || !payload.ok) {
      renderError(payload && payload.error ? payload.error.message : 'Failed to load messages');
      return;
    }
    const topics = payload.data.topics || [];
    renderTopicSelect(topics);
    if (reset) {
      messageList.innerHTML = '';
      if (!payload.data.messages.length) renderEmpty('No messages yet. Be the first to say something!');
    } else {
      const before = messageList.querySelector('.message');
      for (const m of payload.data.messages) {
        messageList.insertBefore(renderMessage(m), before);
      }
    }
    const msgs = payload.data.messages;
    if (msgs.length) {
      attachIds(msgs);
    } else {
      state.hasMore = false;
    }
    markVisibleRead();
  }

  function firstMessageId() {
    const first = messageList.querySelector('.message');
    return first ? Number(first.getAttribute('data-id')) : undefined;
  }

  function attachIds(messages) {
    for (const m of messages) {
      const node = messageList.querySelector('[data-id="' + m.id + '"]');
      if (node) node.setAttribute('data-client-id', m.client_msg_id || '');
    }
  }

  function renderTopicSelect(topics) {
    const select = $('topic-select');
    select.innerHTML = '';
    const all = new Map();
    all.set('__general__', 'general');
    for (const t of topics) all.set(String(t.id), t.name);
    for (const [key, name] of all.entries()) {
      const option = document.createElement('option');
      option.value = key;
      option.textContent = name;
      if (key === state.currentTopic) option.selected = true;
      select.appendChild(option);
    }
  }

  function setLoading() {
    state.loading = true;
    if (!messageList.querySelector('.message') && !messageList.querySelector('.loading-line')) {
      const line = el('div', 'loading-line muted', 'Loading messages…');
      line.id = 'loading-line';
      messageList.appendChild(line);
    }
  }

  function clearLoading() {
    state.loading = false;
    const line = $('loading-line');
    if (line) line.remove();
  }

  function renderEmpty(text) {
    messageList.innerHTML = '';
    const node = el('div', 'empty-state', text);
    node.id = 'empty-state';
    messageList.appendChild(node);
  }

  function renderError(text) {
    if (!messageList.querySelector('.empty-state')) {
      messageList.appendChild(el('div', 'empty-state flash flash-error', text));
    }
  }

  /* ---------------------------------------------------------- messages */

  function renderMessage(m) {
    const node = el('div', 'message' + (m.sender && m.sender.id === CURRENT_USER.id ? ' mine' : '') + (m.is_deleted ? ' deleted' : ''));
    node.setAttribute('data-id', m.id);
    if (m.client_msg_id) node.setAttribute('data-client-id', m.client_msg_id);

    const meta = el('div', 'meta');
    meta.appendChild(el('span', 'sender', m.sender ? userLabel(m.sender) : 'unknown'));
    if (m.topic && m.topic !== 'general') meta.appendChild(el('span', 'topic-chip', 'topic: ' + m.topic));
    meta.appendChild(el('span', 'when', fmtTime(m.created_at)));
    if (m.is_edited) meta.appendChild(el('span', 'edited', 'edited'));

    const actions = el('span', 'actions');
    if (m.sender && m.sender.id === CURRENT_USER.id && !m.is_deleted) {
      const editBtn = el('button', 'btn btn-small', 'Edit');
      editBtn.addEventListener('click', () => editMessage(m));
      const delBtn = el('button', 'btn btn-small btn-danger', 'Delete');
      delBtn.addEventListener('click', () => deleteMessage(m));
      actions.appendChild(editBtn);
      actions.appendChild(delBtn);
    }
    meta.appendChild(actions);
    node.appendChild(meta);

    const body = el('div', 'body', m.is_deleted ? 'Message deleted' : m.body);
    node.appendChild(body);
    renderInlinePreviews(node, m.body);
    return node;
  }

  function renderInlinePreviews(node, body) {
    const matches = String(body || '').match(/https?:\/\/\S+/g) || [];
    for (const url of matches.slice(0, 2)) {
      (async () => {
        const { payload } = await apiFetch('/api/chat/link_preview?url=' + encodeURIComponent(url));
        if (payload && payload.ok && payload.data.preview) {
          const p = payload.data.preview;
          const box = el('div', 'preview-card small');
          box.appendChild(el('div', 'preview-title', p.title || p.url));
          box.appendChild(el('div', 'muted small', p.site_name + ' · ' + p.url));
          node.appendChild(box);
        }
      })();
    }
  }

  function editMessage(m) {
    const next = prompt('Edit message', m.body);
    if (next === null) return;
    sendSocket({ type: 'edit', slug: state.currentWorkspace, message_id: m.id, body: next });
  }

  function deleteMessage(m) {
    if (!confirm('Delete this message?')) return;
    sendSocket({ type: 'delete', slug: state.currentWorkspace, message_id: m.id });
  }

  async function sendMessage() {
    const input = $('compose-input');
    const body = input.value.trim();
    if (!body) return;
    const clientMsgId = crypto.randomUUID ? crypto.randomUUID() : ('c' + Date.now() + Math.random().toString(16).slice(2));
    state.pendingClientIds.add(clientMsgId);
    const topicId = $('topic-select').value === '__general__' ? null : Number($('topic-select').value);
    $('send-state').textContent = 'sending…';
    const sent = sendSocket({
      type: 'message',
      slug: state.currentWorkspace,
      channel_id: state.currentChannel,
      topic_id: topicId,
      body: body,
      client_msg_id: clientMsgId,
    });
    if (!sent) {
      $('send-state').textContent = 'offline — message not sent, reconnecting';
      reportFrontend('api_error');
    } else {
      input.value = '';
    }
    setTimeout(() => { $('send-state').textContent = ''; }, 2500);
  }

  /* -------------------------------------------------- server events */

  function handleServerMessage(msg) {
    switch (msg.type) {
      case 'hello':
        break;
      case 'subscribed':
        reportFrontend('connected');
        break;
      case 'presence':
        state.online = new Set(msg.online || []);
        renderPresence();
        break;
      case 'message_new': {
        const m = msg.message;
        if (m.channel_id === state.currentChannel) {
          const existing = messageList.querySelector('[data-id="' + m.id + '"]');
          if (existing) return;
          messageList.appendChild(renderMessage(m));
          hideEmptyState();
          state.hasMore = true;
          markVisibleRead();
        } else {
          bumpChannel(m.channel_id);
        }
        break;
      }
      case 'message_edited': {
        const m = msg.message;
        const node = messageList.querySelector('[data-id="' + m.id + '"]');
        if (node) node.replaceWith(renderMessage(m));
        break;
      }
      case 'message_deleted': {
        const node = messageList.querySelector('[data-id="' + msg.message_id + '"]');
        if (node) {
          node.classList.add('deleted');
          node.querySelector('.body').textContent = 'Message deleted';
        }
        break;
      }
      case 'typing':
        showTyping(msg);
        break;
      case 'ack':
        handleAck(msg);
        break;
      case 'error':
        handleServerError(msg);
        break;
      default:
        break;
    }
  }

  function handleAck(msg) {
    if (msg.duplicate) {
      reportFrontend('duplicate');
      return;
    }
    if (msg.message) {
      const node = messageList.querySelector('[data-client-id="' + (msg.message.client_msg_id || '__x__') + '"]');
      if (node) node.setAttribute('data-id', msg.message.id);
      state.pendingClientIds.delete(msg.client_msg_id);
    }
  }

  function handleServerError(msg) {
    if (msg.client_msg_id) {
      const node = messageList.querySelector('[data-client-id="' + msg.client_msg_id + '"]');
      if (node) node.querySelector('.body').textContent = '❌ ' + (msg.message || 'failed');
    } else {
      const line = el('div', 'flash flash-error', 'Error: ' + (msg.message || msg.code));
      messageList.appendChild(line);
    }
    reportFrontend('api_error');
  }

  function hideEmptyState() {
    const empty = messageList.querySelector('.empty-state');
    if (empty) empty.remove();
  }

  function bumpChannel(channelId) {
    const item = $('channel-list').querySelector('.nav-item');
    // lightweight: no badge infrastructure — presence on selected view is enough
  }

  function markVisibleRead() {
    const messages = messageList.querySelectorAll('.message[data-id]');
    const recent = messages[messages.length - 1];
    if (recent) {
      const id = Number(recent.getAttribute('data-id'));
      sendSocket({ type: 'read', message_id: id });
    }
  }

  /* -------------------------------------------------- presence / typing */

  function renderPresence() {
    const container = $('presence-list');
    container.innerHTML = '';
    if (!state.online.size) {
      container.appendChild(el('div', 'muted small', 'Nobody online right now.'));
      return;
    }
    const names = [];
    for (const uid of state.online) {
      names.push(uid === CURRENT_USER.id ? 'you' : 'user #' + uid);
    }
    container.appendChild(el('div', 'small', '● ' + names.join(', ')));
  }

  function showTyping(msg) {
    const indicator = $('typing-indicator');
    const who = msg.user && msg.user.id === CURRENT_USER.id ? 'You' : (msg.user ? msg.user.display_name : 'Someone');
    indicator.textContent = who + ' is typing…';
    clearTimeout(state.typingTimers[msg.user ? msg.user.id : 0]);
    state.typingTimers[msg.user ? msg.user.id : 0] = setTimeout(() => {
      indicator.textContent = '';
    }, 3000);
  }

  function onComposeInput() {
    if (state.currentChannel && state.ws && state.ws.readyState === WebSocket.OPEN) {
      sendSocket({ type: 'typing', channel_id: state.currentChannel });
    }
  }

  /* ---------------------------------------------------------- search */

  async function runSearch() {
    const q = $('search-input').value.trim();
    if (!q) return;
    const results = $('search-results');
    results.classList.remove('hidden');
    const body = $('search-results-body');
    body.innerHTML = '';
    body.appendChild(el('div', 'muted small', 'Searching…'));
    const query = new URLSearchParams({ q: q, include_dms: 'true' });
    if (state.currentWorkspace) query.set('slug', state.currentWorkspace);
    const { payload } = await apiFetch('/api/chat/message_history_and_search?' + query.toString());
    body.innerHTML = '';
    if (!payload || !payload.ok) {
      body.appendChild(el('div', 'flash flash-error', 'Search failed'));
      return;
    }
    reportFrontend('search');
    let total = 0;
    for (const entry of payload.data.channel_results || []) {
      const h = el('div', 'small', '— ' + entry.workspace.name + ' —');
      body.appendChild(h);
      for (const m of entry.matches || []) {
        const line = el('div', 'small', '[' + fmtTime(m.created_at) + '] ' + (m.sender ? m.sender.username : '?') + ' in #' + (m.topic || 'general') + ': ' + m.body);
        line.addEventListener('click', () => {
          state.currentChannel = m.channel_id;
          state.currentTopic = String(m.topic_id || '__general__');
          switchChannel(m.channel_id);
        });
        body.appendChild(line);
        total++;
      }
    }
    for (const m of payload.data.dm_results || []) {
      body.appendChild(el('div', 'small', '[' + fmtTime(m.created_at) + '] DM ' + (m.sender ? m.sender.username : '?') + ': ' + m.body));
      total++;
    }
    if (!total) body.appendChild(el('div', 'muted small', 'No results found.'));
    else body.insertBefore(el('div', 'small muted', total + ' result(s)'), body.firstChild);
  }

  function hideSearch() {
    $('search-results').classList.add('hidden');
  }

  /* -------------------------------------------------------- frontend */

  async function reportFrontend(eventType, payload) {
    try {
      await apiFetch('/api/chat/frontend_api_integration', {
        method: 'POST',
        body: { event_type: eventType, payload: payload || { view: 'chat' } },
      });
    } catch (_) { /* best-effort */ }
  }

  /* ---------------------------------------------------------- init */

  function bindEvents() {
    $('compose-form').addEventListener('submit', (e) => { e.preventDefault(); sendMessage(); });
    $('compose-input').addEventListener('input', onComposeInput);
    $('topic-select').addEventListener('change', () => {
      state.currentTopic = $('topic-select').value;
      state.hasMore = true;
      loadMessages(true);
    });
    $('search-btn').addEventListener('click', runSearch);
    $('search-input').addEventListener('keydown', (e) => { if (e.key === 'Enter') runSearch(); });
    $('search-close').addEventListener('click', hideSearch);
    messageList.addEventListener('scroll', () => {
      if (messageList.scrollTop < 30 && state.hasMore && !state.loading) {
        loadMessages(false);
      }
    });
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden && state.ws && state.ws.readyState === WebSocket.OPEN) {
        sendSocket({ type: 'presence' });
      }
    });
  }

  async function init() {
    bindEvents();
    if (boot.searchMode) {
      // Search page: focus the search box
      setTimeout(() => $('search-input').focus(), 200);
    }
    connect();
    await loadWorkspaces();
  }

  init();
})();
