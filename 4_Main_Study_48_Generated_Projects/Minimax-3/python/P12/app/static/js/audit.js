// Audit and lineage page.
(function () {
  const typeSelect = document.getElementById('audit-entity-type');
  const idInput = document.getElementById('audit-entity-id');
  const refresh = document.getElementById('audit-refresh');
  const auditList = document.getElementById('audit-list');
  const lineageList = document.getElementById('lineage-list');

  async function load() {
    const params = new URLSearchParams();
    if (typeSelect.value) params.set('entity_type', typeSelect.value);
    if (idInput.value) params.set('entity_id', idInput.value);
    const url = '/api/data/audit_and_lineage' + (params.toString() ? '?' + params.toString() : '');
    try {
      const response = await App.api.get(url);
      const events = response.data.audit || [];
      const lineage = response.data.lineage || [];
      auditList.innerHTML = events.map(function (event) {
        return `<li>
          <strong>${App.escapeHtml(event.action)}</strong>
          <small>${App.escapeHtml(event.entity_type)} #${App.escapeHtml(event.entity_id)} by ${App.escapeHtml(event.actor_username)}</small>
          <small>${App.formatDate(event.created_at)} — ${App.escapeHtml(event.summary)}</small>
        </li>`;
      }).join('') || '<li class="muted">No audit events matched.</li>';
      lineageList.innerHTML = lineage.map(function (record) {
        return `<li>
          <strong>${App.escapeHtml(record.transformation || 'edge')}</strong>
          <small>${App.escapeHtml(record.parent_type)}#${App.escapeHtml(record.parent_id)} → ${App.escapeHtml(record.child_type)}#${App.escapeHtml(record.child_id)}</small>
          <small>${App.formatDate(record.created_at)}</small>
        </li>`;
      }).join('') || '<li class="muted">No lineage edges recorded.</li>';
    } catch (err) {
      auditList.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  if (refresh) refresh.addEventListener('click', load);
  if (typeSelect) typeSelect.addEventListener('change', load);
  load();
})();
