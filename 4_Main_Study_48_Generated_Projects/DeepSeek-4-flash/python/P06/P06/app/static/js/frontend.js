/* Frontend API integration client (CHAT-11). */

(function () {
  'use strict';

  const $ = (id) => document.getElementById(id);

  async function loadStatus() {
    const { payload } = await apiFetch('/api/chat/frontend_api_integration');
    const status = $('api-status');
    if (!payload || !payload.ok) {
      status.textContent = 'API status unavailable.';
      return;
    }
    const data = payload.data;
    status.textContent =
      'User: ' + (data.current_user ? data.current_user.username : 'anonymous') +
      ' · WS: ' + data.ws_url +
      ' · Server time: ' + data.server_time +
      ' · Features: ' + Object.keys(data.features || {}).join(', ');
    renderEvents(data.frontend_events || []);
  }

  function renderEvents(events) {
    const container = $('frontend-events');
    container.innerHTML = '';
    if (!events.length) {
      container.appendChild(el('div', 'muted small', 'No frontend events recorded yet.'));
      return;
    }
    for (const event of events) {
      const line = el('div', 'small', '#' + event.id + ' · ' + event.event_type + ' · ' +
        (event.payload && event.payload.view ? 'view=' + event.payload.view : '') + ' · ' +
        (event.acknowledged ? 'acknowledged' : 'open') + ' · ' + fmtTime(event.created_at));
      if (!event.acknowledged) {
        const btn = el('button', 'btn btn-small', 'Ack');
        btn.style.marginLeft = '8px';
        btn.addEventListener('click', async () => {
          await apiFetch('/api/chat/frontend_api_integration/' + event.id, { method: 'PATCH', body: {} });
          loadStatus();
        });
        line.appendChild(btn);
      }
      container.appendChild(line);
    }
  }

  function bind() {
    $('frontend-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      let payload = {};
      const raw = $('frontend-payload').value.trim();
      if (raw) {
        try { payload = JSON.parse(raw); } catch (_) { payload = { raw: raw }; }
      }
      const { response } = await apiFetch('/api/chat/frontend_api_integration', {
        method: 'POST',
        body: { event_type: $('frontend-event-type').value, payload: payload },
      });
      if (response.ok) {
        $('frontend-payload').value = '';
        loadStatus();
      }
    });
  }

  bind();
  loadStatus();
})();
