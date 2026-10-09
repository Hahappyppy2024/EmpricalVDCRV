// Dashboards list page.
(function () {
  async function loadDatasets() {
    const list = document.getElementById('dashboard-datasets');
    if (!list) return;
    try {
      const response = await App.api.get('/api/data/dataset_catalog');
      const items = (response.data.datasets || []);
      list.innerHTML = items.map(function (dataset) {
        return `<li>
          <strong>${App.escapeHtml(dataset.name)}</strong>
          <small>${dataset.row_count} rows · ${dataset.column_count} cols · ${App.escapeHtml(dataset.visibility)}</small>
          <a class="btn" href="/dashboards/${dataset.id}">Open dashboard</a>
        </li>`;
      }).join('') || '<li class="muted">No datasets visible to you.</li>';
    } catch (err) {
      list.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  async function loadCharts() {
    const list = document.getElementById('dashboard-charts');
    if (!list) return;
    try {
      const datasets = (await App.api.get('/api/data/dataset_catalog')).data.datasets || [];
      const allCharts = [];
      for (const dataset of datasets) {
        try {
          const resp = await App.api.get('/api/data/chart_builder?dataset_id=' + dataset.id);
          (resp.data.charts || []).forEach(function (chart) {
            allCharts.push(Object.assign({}, chart, { dataset_name: dataset.name }));
          });
        } catch (err) {
          // ignore — keep going
        }
      }
      list.innerHTML = allCharts.map(function (chart) {
        return `<li>
          <strong>${App.escapeHtml(chart.name)}</strong>
          <small>${App.escapeHtml(chart.dataset_name)} · ${App.escapeHtml(chart.chart_type)}</small>
        </li>`;
      }).join('') || '<li class="muted">No charts configured yet.</li>';
    } catch (err) {
      list.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  async function loadShares() {
    const list = document.getElementById('dashboard-shares');
    if (!list) return;
    try {
      const response = await App.api.get('/api/data/dashboard_sharing');
      const shares = response.data.shares || [];
      list.innerHTML = shares.map(function (share) {
        return `<li>
          <strong>${App.escapeHtml(share.description || 'Share ' + share.id)}</strong>
          <small>audience=${App.escapeHtml(share.audience)} permission=${App.escapeHtml(share.permission)} · revoked=${share.revoked ? 'yes' : 'no'}</small>
        </li>`;
      }).join('') || '<li class="muted">No active shares.</li>';
    } catch (err) {
      list.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  loadDatasets();
  loadCharts();
  loadShares();
})();
