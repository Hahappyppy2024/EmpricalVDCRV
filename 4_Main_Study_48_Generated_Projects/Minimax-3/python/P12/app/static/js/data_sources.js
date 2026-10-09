// Data sources page.
(function () {
  const listEl = document.getElementById('data-source-list');
  const form = document.getElementById('data-source-form');
  const probeEl = document.getElementById('data-source-probe');
  const refresh = document.getElementById('data-source-refresh');

  async function load() {
    try {
      const response = await App.api.get('/api/data/data_source_connections');
      const sources = response.data.data_sources || [];
      listEl.innerHTML = sources.map(function (source) {
        return `<li>
          <strong>${App.escapeHtml(source.name)}</strong>
          <small>${App.escapeHtml(source.kind)} · ${source.enabled ? 'enabled' : 'disabled'}</small>
          <button class="btn" data-probe="${source.id}">Probe</button>
          <button class="btn btn-danger" data-delete="${source.id}">Delete</button>
        </li>`;
      }).join('') || '<li class="muted">No data sources configured.</li>';
      listEl.querySelectorAll('[data-probe]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
          try {
            const response = await App.api.post('/api/data/data_source_connections/' + btn.getAttribute('data-probe') + '/probe', {});
            probeEl.textContent = JSON.stringify(response.data, null, 2);
          } catch (err) {
            probeEl.textContent = err.message;
          }
        });
      });
      listEl.querySelectorAll('[data-delete]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
          try {
            await App.api.del('/api/data/data_source_connections/' + btn.getAttribute('data-delete'));
            load();
          } catch (err) {
            alert(err.message);
          }
        });
      });
    } catch (err) {
      listEl.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  if (form) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const data = {};
      new FormData(form).forEach(function (value, key) { data[key] = value; });
      data.dataset_id = Number(data.dataset_id);
      data.config = { dataset_id: data.dataset_id };
      try {
        await App.api.post('/api/data/data_source_connections', data);
        form.reset();
        load();
      } catch (err) {
        alert(err.message);
      }
    });
  }

  if (refresh) refresh.addEventListener('click', load);
  load();
})();
