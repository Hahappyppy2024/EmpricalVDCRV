/* Direct messages client (CHAT-06, CHAT-11). */

(function () {
  'use strict';

  const boot = window.DM_BOOT || {};
  const CURRENT_USER = boot.currentUser || { id: 0, username: '?' };
  let state = {
    ws: null,
    connected: false,
    threads: [],
    currentThread: null,
    reconnectAttempts: 0,
    typingTimers: {},
  };

  const $ = (id) => document.getElementById(id);
  const list = $('dm-message-list');
  const empty = $('dm-empty');
  const connStatus = $('conn-status');

  function connect() {
    const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
    const ws = new WebSocket(protocol + '//' + location.host + '/ws/chat');
    state.ws = ws;
    ws.onopen = function () {
      state.connected = true;
      state.reconnectAttempts = 0;
      connStatus.textContent = 'online';
      connStatus.className = 'conn-status online';
      send({ type: 'subscribe', workspace_slug: null, channels: [], threads: state.threads.map((t) => t.id) });
    };
    ws.onmessage = function (event) {
      let msg;
      try { msg = JSON.parse(event.data); } catch (_) { return; }
      handle(msg);
    };
    ws.onclose = function () {
      state.connected = false;
      connStatus.textContent = 'offline — reconnecting…';
      connStatus.className = 'conn-status offline';
      const delay = Math.min(1000 * Math.pow(2, state.reconnectAttempts), 15000);
      state.reconnectAttempts += 1;
      setTimeout(connect, delay);
    };
  }

  function send(payload) {
    if (state.ws && state.ws.readyState === WebSocket.OPEN) {
      try { state.ws.send(JSON.stringify(payload)); } catch (_) { /* noop */ }
    }
  }

  function handle(msg) {
    if (msg.type === 'dm_new' || msg.type === 'dm_edited' || msg.type === 'dm_deleted') {
      if (msg.thread_id === state.currentThread) loadThread(state.currentThread);
      loadThreads();
    } else if (msg.type === 'typing' && msg.thread_id === state.currentThread) {
      const who = msg.user && msg.user.id === CURRENT_USER.id ? 'You' : (msg.user ? msg.user.display_name : 'Someone');
      $('dm-typing-indicator').textContent = who + ' is typing…';
      clearTimeout(state.typingTimers[msg.user ? msg.user.id : 0]);
      state.typingTimers[msg.user ? msg.user.id : 0] = setTimeout(() => { $('dm-typing-indicator').textContent = ''; }, 3000);
    } else if (msg.type === 'error') {
      const line = document.createElement('div');
      line.className = 'flash flash-error';
      line.textContent = 'Error: ' + (msg.message || msg.code);
      list.appendChild(line);
    }
  }

  async function loadThreads() {
    const { payload } = await apiFetch('/api/chat/direct_messages');
    if (!payload || !payload.ok) return;
    state.threads = payload.data.threads || [];
    const container = $('dm-threads');
    container.innerHTML = '';
    if (!state.threads.length) {
      container.appendChild(el('div', 'muted small', 'No conversations yet.'));
      return;
    }
    for (const thread of state.threads) {
      const line = el('div', 'dm-line nav-item' + (thread.id === state.currentThread ? ' active' : ''));
      const label = el('span', '', thread.other + ' (' + thread.other_user_id + ')');
      const info = el('span', 'small', (thread.unread ? 'unread ' + thread.unread : ''));
      line.appendChild(label);
      line.appendChild(info);
      line.addEventListener('click', () => loadThread(thread.id));
      container.appendChild(line);
    }
    if (state.threads.length && !state.currentThread) {
      loadThread(state.threads[0].id);
    }
  }

  async function loadThread(threadId) {
    state.currentThread = threadId;
    const { payload } = await apiFetch('/api/chat/direct_messages?thread_id=' + threadId);
    $('dm-title').textContent = 'Direct messages';
    $('dm-subtitle').textContent = payload && payload.data && payload.data.other ? payload.data.other.display_name : 'Conversation';
    list.innerHTML = '';
    if (!payload || !payload.ok) {
      list.appendChild(el('div', 'flash flash-error', 'Failed to load conversation'));
      return;
    }
    const messages = payload.data.messages || [];
    if (!messages.length) {
      list.appendChild(el('div', 'empty-state', 'No messages yet in this conversation.'));
    }
    for (const m of messages) {
      list.appendChild(renderMessage(m));
    }
    loadThreads();
    send({ type: 'dm_read', thread_id: threadId });
  }

  function renderMessage(m) {
    const node = el('div', 'message' + (m.sender && m.sender.id === CURRENT_USER.id ? ' mine' : '') + (m.is_deleted ? ' deleted' : ''));
    const meta = el('div', 'meta');
    meta.appendChild(el('span', 'sender', m.sender ? userLabel(m.sender) : '?'));
    meta.appendChild(el('span', 'when', fmtTime(m.created_at)));
    if (m.is_edited) meta.appendChild(el('span', 'edited', 'edited'));
    node.appendChild(meta);
    node.appendChild(el('div', 'body', m.is_deleted ? 'Message deleted' : m.body));
    return node;
  }

  async function sendMessage() {
    const input = $('dm-compose-input');
    const body = input.value.trim();
    if (!body || !state.currentThread) return;
    const clientMsgId = crypto.randomUUID ? crypto.randomUUID() : ('d' + Date.now());
    send({ type: 'dm', recipient: $('dm-subtitle').textContent === 'Conversation' ? '' : '', body: body, client_msg_id: clientMsgId });
    // Fallback if recipient unknown: derive from thread
    const thread = state.threads.find((t) => t.id === state.currentThread);
    if (thread) {
      send({ type: 'dm', recipient: String(thread.other_user_id), body: body, client_msg_id: clientMsgId });
    }
    input.value = '';
  }

  async function startConversation(username) {
    const { payload } = await apiFetch('/api/chat/direct_messages', {
      method: 'POST',
      body: { recipient: username, body: ' ' },
    });
    if (payload && payload.ok) {
      await loadThreads();
      loadThread(payload.data.thread_id);
    }
  }

  function bind() {
    $('dm-compose-form').addEventListener('submit', (e) => { e.preventDefault(); sendMessage(); });
    $('dm-compose-input').addEventListener('input', () => {
      if (state.currentThread) send({ type: 'typing', thread_id: state.currentThread });
    });
    $('dm-start-form').addEventListener('submit', (e) => {
      e.preventDefault();
      const username = $('dm-recipient').value.trim();
      if (username) startConversation(username);
    });
  }

  (async function init() {
    bind();
    connect();
    await loadThreads();
  })();
})();
