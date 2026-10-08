/* Connection and message-handling client (CHAT-10). */

(function () {
  'use strict';

  const $ = (id) => document.getElementById(id);

  async function loadState() {
    const { payload } = await apiFetch('/api/chat/connection_and_message_handling');
    if (!payload || !payload.ok) return;
    const data = payload.data;

    const summary = $('delivery-summary');
    summary.innerHTML = '';
    const counts = data.delivery_counts || { sent: 0, delivered: 0, read: 0, failed: 0 };
    for (const key of ['sent', 'delivered', 'read', 'failed']) {
      const cell = el('div', 'summary-cell');
      cell.appendChild(el('div', 'num', String(counts[key] || 0)));
      cell.appendChild(el('div', 'lbl', key));
      summary.appendChild(cell);
    }
    const online = el('div', 'summary-cell');
    online.appendChild(el('div', 'num', String((data.online_user_ids || []).length)));
    online.appendChild(el('div', 'lbl', 'users online'));
    summary.appendChild(online);

    const records = $('delivery-records');
    records.innerHTML = '';
    if (!data.delivery_records.length) records.appendChild(el('div', 'muted small', 'No delivery records yet.'));
    for (const record of data.delivery_records) {
      records.appendChild(el('div', 'small',
        '#' + record.id + ' · ' + (record.message_id ? 'message ' + record.message_id : 'dm ' + record.direct_message_id) +
        ' · ' + record.status + ' · ' + fmtTime(record.updated_at)));
    }

    const events = $('connection-events');
    events.innerHTML = '';
    if (!data.connection_events.length) events.appendChild(el('div', 'muted small', 'No connection events yet.'));
    for (const event of data.connection_events) {
      events.appendChild(el('div', 'small',
        '#' + event.id + ' · ' + event.event_type + (event.message_id ? ' (message ' + event.message_id + ')' : '') +
        ' · ' + fmtTime(event.created_at)));
    }
  }

  function bind() {
    $('event-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const messageId = $('event-message-id').value ? Number($('event-message-id').value) : null;
      const { payload } = await apiFetch('/api/chat/connection_and_message_handling', {
        method: 'POST',
        body: { event_type: $('event-type').value, message_id: messageId },
      });
      if (payload && payload.ok) await loadState();
    });
  }

  bind();
  loadState();
})();
