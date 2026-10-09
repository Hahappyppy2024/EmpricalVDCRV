// Dashboard detail page.
(function () {
  const datasetId = window.DASHBOARD_ID;
  if (!datasetId) return;

  async function loadDataset() {
    try {
      const response = await App.api.get('/api/data/data_preview?dataset_id=' + datasetId + '&limit=5');
      const dataset = response.data.dataset;
      document.getElementById('dash-title').textContent = dataset.name;
      document.getElementById('dash-meta').textContent = dataset.description || '';
    } catch (err) {
      document.getElementById('dash-title').textContent = 'Dashboard unavailable';
      document.getElementById('dash-meta').textContent = err.message;
    }
  }

  async function loadCharts() {
    const container = document.getElementById('dash-charts');
    try {
      const response = await App.api.get('/api/data/chart_builder?dataset_id=' + datasetId);
      const charts = response.data.charts || [];
      container.innerHTML = charts.map(function (chart) {
        return `<article class="card">
          <header><h3>${App.escapeHtml(chart.name)}</h3></header>
          <p class="muted">${App.escapeHtml(chart.chart_type)} · ${App.escapeHtml((chart.config.x || '') + ' / ' + (chart.config.y || ''))}</p>
          <pre class="code-output">${App.escapeHtml(JSON.stringify(chart.computed || {}, null, 2))}</pre>
        </article>`;
      }).join('') || '<p class="muted">No charts configured for this dashboard yet.</p>';
    } catch (err) {
      container.innerHTML = `<div class="error-banner">${App.escapeHtml(err.message)}</div>`;
    }
  }

  async function loadShares() {
    const list = document.getElementById('dash-shares');
    try {
      const response = await App.api.get('/api/data/dashboard_sharing?dataset_id=' + datasetId);
      const shares = response.data.shares || [];
      list.innerHTML = shares.map(function (share) {
        return `<li>
          <strong>${App.escapeHtml(share.description || 'Share ' + share.id)}</strong>
          <small>audience=${App.escapeHtml(share.audience)} permission=${App.escapeHtml(share.permission)}</small>
        </li>`;
      }).join('') || '<li class="muted">No shares configured for this dataset.</li>';
    } catch (err) {
      list.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  loadDataset();
  loadCharts();
  loadShares();
})();
