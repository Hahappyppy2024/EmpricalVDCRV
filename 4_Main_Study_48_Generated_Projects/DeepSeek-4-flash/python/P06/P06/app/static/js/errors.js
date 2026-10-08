/* Errors client (CHAT-12). */

(function () {
  'use strict';

  const $ = (id) => document.getElementById(id);

  async function loadErrors() {
    const { payload } = await apiFetch('/api/chat/errors');
    const container = $('error-records');
    container.innerHTML = '';
    if (!payload || !payload.ok) {
      container.appendChild(el('div', 'muted small', 'Could not load errors.'));
      return;
    }
    const records = payload.data.error_records || [];
    if (!records.length) {
      container.appendChild(el('div', 'muted small', 'No error records yet.'));
      return;
    }
    for (const record of records) {
      const line = el('div', 'small', '#' + record.id + ' · ' + record.code + ' · ' + record.message +
        (record.path ? ' · ' + record.path : '') + ' · ' + (record.acknowledged ? 'acknowledged' : 'open') + ' · ' + fmtTime(record.created_at));
      if (!record.acknowledged) {
        const btn = el('button', 'btn btn-small', 'Acknowledge');
        btn.style.marginLeft = '8px';
        btn.addEventListener('click', async () => {
          await apiFetch('/api/chat/errors/' + record.id, { method: 'PATCH', body: {} });
          loadErrors();
        });
        line.appendChild(btn);
      }
      container.appendChild(line);
    }
  }

  function bind() {
    $('error-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const { response } = await apiFetch('/api/chat/errors', {
        method: 'POST',
        body: {
          code: $('error-code').value,
          message: $('error-message').value,
          path: $('error-path').value,
        },
      });
      if (response.ok) {
        $('error-code').value = '';
        $('error-message').value = '';
        $('error-path').value = '';
        loadErrors();
      }
    });
  }

  bind();
  loadErrors();
})();
