// Dataset detail page: preview, filters, charts, calculated columns, exports, shares.
(function () {
  const datasetId = window.DATASET_ID;
  if (!datasetId) return;

  const tabs = document.querySelectorAll('.tab-btn');
  tabs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      tabs.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      const target = btn.getAttribute('data-tab');
      document.querySelectorAll('.tab-panel').forEach(function (panel) {
        panel.classList.toggle('active', panel.getAttribute('data-tab') === target);
      });
      if (target === 'filter') loadFilters();
      if (target === 'chart') loadCharts();
      if (target === 'calc') loadCalcColumns();
      if (target === 'export') loadExports();
      if (target === 'share') loadShares();
    });
  });

  let schemaColumns = [];

  async function loadPreview() {
    const limitNode = document.getElementById('preview-limit');
    const offsetNode = document.getElementById('preview-offset');
    const statusNode = document.getElementById('preview-status');
    const params = new URLSearchParams({
      dataset_id: datasetId,
      limit: limitNode.value,
      offset: offsetNode.value
    });
    try {
      const response = await App.api.get('/api/data/data_preview?' + params.toString());
      renderDataset(response.data.dataset);
      renderSummary(response.data.summary);
      renderRows(response.data.rows);
      App.status(statusNode,
        `Showing ${response.data.rows.length} of ${response.data.total_rows} rows (offset ${response.data.offset}).`,
        'success');
      schemaColumns = (response.data.dataset.schema || []).map(function (c) { return c.name; });
    } catch (err) {
      App.status(statusNode, err.message, 'error');
    }
  }

  function renderDataset(dataset) {
    document.getElementById('dataset-title').textContent = dataset.name;
    const tags = (dataset.tags || []).map(function (t) { return `<span class="tag-pill">${App.escapeHtml(t)}</span>`; }).join('');
    document.getElementById('dataset-meta').innerHTML = `${App.escapeHtml(dataset.description || '')} · ${tags} · ${dataset.row_count} rows`;
  }

  function renderSummary(summary) {
    const container = document.getElementById('preview-summary');
    if (!summary || !summary.columns) {
      container.innerHTML = '';
      return;
    }
    container.innerHTML = summary.columns.map(function (col) {
      const extras = [];
      if (typeof col.min === 'number') extras.push(`min=${col.min}`);
      if (typeof col.max === 'number') extras.push(`max=${col.max}`);
      if (typeof col.mean === 'number') extras.push(`mean=${col.mean.toFixed(2)}`);
      if (typeof col.distinct === 'number') extras.push(`distinct=${col.distinct}`);
      if (typeof col.missing === 'number') extras.push(`missing=${col.missing}`);
      return `<div class="summary-tile">
        <strong>${App.escapeHtml(col.name)}</strong>
        <small>${App.escapeHtml(col.type)} · ${extras.join(' · ')}</small>
      </div>`;
    }).join('');
  }

  function renderRows(rows) {
    const table = document.getElementById('preview-table');
    if (!rows.length) {
      table.innerHTML = '<tbody><tr><td>No rows to display.</td></tr></tbody>';
      return;
    }
    const headers = Object.keys(rows[0]);
    const thead = `<tr>${headers.map(function (h) { return `<th>${App.escapeHtml(h)}</th>`; }).join('')}</tr>`;
    const tbody = rows.map(function (row) {
      return `<tr>${headers.map(function (h) {
        const value = row[h];
        return `<td>${App.escapeHtml(value === null || value === undefined ? '' : String(value))}</td>`;
      }).join('')}</tr>`;
    }).join('');
    table.innerHTML = `<thead>${thead}</thead><tbody>${tbody}</tbody>`;
  }

  document.getElementById('preview-refresh').addEventListener('click', loadPreview);
  loadPreview();

  // Filter builder
  const filterForm = document.getElementById('filter-form');
  const filterRunForm = document.getElementById('filter-run-form');
  const filterListEl = document.getElementById('filter-list');
  const filterRunResultEl = document.getElementById('filter-run-result');

  async function loadFilters() {
    try {
      const response = await App.api.get('/api/data/filter_builder?dataset_id=' + datasetId);
      const filters = response.data.filters || [];
      filterListEl.innerHTML = filters.map(function (filter) {
        return `<li>
          <strong>${App.escapeHtml(filter.name)}</strong>
          <small>${App.escapeHtml(filter.description || '')}</small>
          <code>${App.escapeHtml(filter.expression)}</code>
          <button class="btn" data-filter-delete="${filter.id}">Delete</button>
        </li>`;
      }).join('') || '<li class="muted">No saved filters yet.</li>';
      filterListEl.querySelectorAll('[data-filter-delete]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
          try {
            await App.api.del('/api/data/filter_builder/' + btn.getAttribute('data-filter-delete'));
            loadFilters();
          } catch (err) {
            alert(err.message);
          }
        });
      });
    } catch (err) {
      filterListEl.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  if (filterForm) {
    filterForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const data = {};
      new FormData(filterForm).forEach(function (value, key) { data[key] = value; });
      data.dataset_id = datasetId;
      data.is_public = filterForm.querySelector('input[name="is_public"]').checked;
      try {
        await App.api.post('/api/data/filter_builder', data);
        filterForm.reset();
        loadFilters();
      } catch (err) {
        alert(err.message);
      }
    });
  }

  if (filterRunForm) {
    filterRunForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const data = {};
      new FormData(filterRunForm).forEach(function (value, key) { data[key] = value; });
      data.dataset_id = datasetId;
      try {
        const response = await App.api.post('/api/data/filter_builder/run', data);
        filterRunResultEl.textContent = JSON.stringify({
          matched: response.data.matched,
          total_rows: response.data.total_rows,
          sample: (response.data.rows || []).slice(0, 5)
        }, null, 2);
      } catch (err) {
        filterRunResultEl.textContent = err.message;
      }
    });
  }

  // Chart builder
  const chartForm = document.getElementById('chart-form');
  const chartListEl = document.getElementById('chart-list');
  const chartRenderEl = document.getElementById('chart-render');

  async function loadCharts() {
    try {
      const response = await App.api.get('/api/data/chart_builder?dataset_id=' + datasetId);
      const charts = response.data.charts || [];
      chartListEl.innerHTML = charts.map(function (chart) {
        return `<li>
          <strong>${App.escapeHtml(chart.name)}</strong>
          <small>${App.escapeHtml(chart.chart_type)} · ${App.escapeHtml((chart.config.x || '') + ' / ' + (chart.config.y || ''))}</small>
          <button class="btn" data-chart-render="${chart.id}">Render</button>
          <button class="btn btn-danger" data-chart-delete="${chart.id}">Delete</button>
        </li>`;
      }).join('') || '<li class="muted">No charts yet.</li>';
      chartListEl.querySelectorAll('[data-chart-render]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
          const id = btn.getAttribute('data-chart-render');
          try {
            const response = await App.api.get('/api/data/chart_builder');
            const chart = (response.data.charts || []).find(function (c) { return c.id === Number(id); });
            if (chart) renderChart(chart);
          } catch (err) {
            chartRenderEl.innerHTML = `<div class="error-banner">${App.escapeHtml(err.message)}</div>`;
          }
        });
      });
      chartListEl.querySelectorAll('[data-chart-delete]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
          try {
            await App.api.del('/api/data/chart_builder/' + btn.getAttribute('data-chart-delete'));
            loadCharts();
          } catch (err) {
            alert(err.message);
          }
        });
      });
    } catch (err) {
      chartListEl.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  if (chartForm) {
    chartForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const data = {};
      new FormData(chartForm).forEach(function (value, key) { data[key] = value; });
      data.dataset_id = datasetId;
      data.config = { x: data.x, y: data.y, aggregate: data.aggregate };
      delete data.x; delete data.y; delete data.aggregate;
      try {
        await App.api.post('/api/data/chart_builder', data);
        chartForm.reset();
        loadCharts();
      } catch (err) {
        alert(err.message);
      }
    });
  }

  function renderChart(chart) {
    const computed = chart.computed || {};
    if (computed.chart_type === 'bar' || computed.chart_type === 'pie') {
      const series = computed.series || [];
      const max = series.reduce(function (acc, item) { return Math.max(acc, item.value); }, 1);
      const bars = series.map(function (item) {
        const height = Math.max(8, (item.value / max) * 180);
        return `<div class="bar" style="height:${height}px" title="${App.escapeHtml(item.label)}=${item.value}">
          <small>${App.escapeHtml(item.label)} (${Number(item.value).toFixed(1)})</small>
        </div>`;
      }).join('');
      chartRenderEl.innerHTML = `<h3>${App.escapeHtml(chart.name)}</h3>
        <p class="muted">x=${App.escapeHtml(computed.x)} y=${App.escapeHtml(computed.y || '')} aggregate=${App.escapeHtml(computed.aggregate || '')}</p>
        <div class="bar-chart">${bars || '<span class="muted">No data.</span>'}</div>`;
    } else if (computed.chart_type === 'scatter') {
      const points = computed.points || [];
      const svg = renderScatter(points);
      chartRenderEl.innerHTML = `<h3>${App.escapeHtml(chart.name)}</h3>
        <div class="scatter-chart">${svg}</div>`;
    } else {
      chartRenderEl.innerHTML = `<pre class="code-output">${App.escapeHtml(JSON.stringify(computed, null, 2))}</pre>`;
    }
  }

  function renderScatter(points) {
    if (!points.length) return '<svg></svg>';
    const xs = points.map(function (p) { return p.x; });
    const ys = points.map(function (p) { return p.y; });
    const minX = Math.min.apply(null, xs), maxX = Math.max.apply(null, xs);
    const minY = Math.min.apply(null, ys), maxY = Math.max.apply(null, ys);
    const width = 320, height = 200, padding = 24;
    function toX(value) { return padding + (value - minX) / (maxX - minX || 1) * (width - padding * 2); }
    function toY(value) { return height - padding - (value - minY) / (maxY - minY || 1) * (height - padding * 2); }
    const circles = points.map(function (p) {
      return `<circle cx="${toX(p.x).toFixed(2)}" cy="${toY(p.y).toFixed(2)}" r="4" fill="#1f6feb"></circle>`;
    }).join('');
    return `<svg viewBox="0 0 ${width} ${height}"><g>${circles}</g></svg>`;
  }

  // Calculated columns
  const calcForm = document.getElementById('calc-form');
  const calcListEl = document.getElementById('calc-list');

  async function loadCalcColumns() {
    try {
      const response = await App.api.get('/api/data/calculated_columns?dataset_id=' + datasetId);
      const columns = response.data.calculated_columns || [];
      calcListEl.innerHTML = columns.map(function (col) {
        return `<li>
          <strong>${App.escapeHtml(col.name)}</strong>
          <small>${App.escapeHtml(col.return_type)} · ${App.escapeHtml(col.description || '')}</small>
          <code>${App.escapeHtml(col.expression)}</code>
        </li>`;
      }).join('') || '<li class="muted">No calculated columns yet.</li>';
    } catch (err) {
      calcListEl.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  if (calcForm) {
    calcForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const data = {};
      new FormData(calcForm).forEach(function (value, key) { data[key] = value; });
      data.dataset_id = datasetId;
      try {
        await App.api.post('/api/data/calculated_columns', data);
        calcForm.reset();
        loadCalcColumns();
        loadPreview();
      } catch (err) {
        alert(err.message);
      }
    });
  }

  // Export
  const exportForm = document.getElementById('export-form');
  const exportListEl = document.getElementById('export-list');

  async function loadExports() {
    try {
      const response = await App.api.get('/api/data/export?dataset_id=' + datasetId);
      const exports = response.data.exports || [];
      exportListEl.innerHTML = exports.map(function (item) {
        return `<li>
          <strong>${App.escapeHtml(item.export_format.toUpperCase())} export</strong>
          <small>${item.row_count} rows · ${App.formatDate(item.created_at)}</small>
          <a class="btn" href="/api/data/export/${item.id}/download">Download</a>
        </li>`;
      }).join('') || '<li class="muted">No exports yet.</li>';
    } catch (err) {
      exportListEl.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  if (exportForm) {
    exportForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const data = {};
      new FormData(exportForm).forEach(function (value, key) { data[key] = value; });
      data.dataset_id = datasetId;
      try {
        await App.api.post('/api/data/export', data);
        exportForm.reset();
        loadExports();
      } catch (err) {
        alert(err.message);
      }
    });
  }

  // Shares
  const shareForm = document.getElementById('share-form');
  const shareListEl = document.getElementById('share-list');

  async function loadShares() {
    try {
      const response = await App.api.get('/api/data/dashboard_sharing?dataset_id=' + datasetId);
      const shares = response.data.shares || [];
      shareListEl.innerHTML = shares.map(function (share) {
        return `<li>
          <strong>${App.escapeHtml(share.description || 'Share ' + share.id)}</strong>
          <small>audience=${App.escapeHtml(share.audience)} permission=${App.escapeHtml(share.permission)}</small>
          <small>link_token=${App.escapeHtml(share.link_token.slice(0, 12))}… · revoked=${share.revoked ? 'yes' : 'no'}</small>
          <button class="btn btn-danger" data-share-revoke="${share.id}">Revoke</button>
        </li>`;
      }).join('') || '<li class="muted">No shares yet.</li>';
      shareListEl.querySelectorAll('[data-share-revoke]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
          try {
            await App.api.del('/api/data/dashboard_sharing/' + btn.getAttribute('data-share-revoke'));
            loadShares();
          } catch (err) {
            alert(err.message);
          }
        });
      });
    } catch (err) {
      shareListEl.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  if (shareForm) {
    shareForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const data = {};
      new FormData(shareForm).forEach(function (value, key) { data[key] = value; });
      data.dataset_id = datasetId;
      if (data.ttl_hours === '') delete data.ttl_hours;
      try {
        await App.api.post('/api/data/dashboard_sharing', data);
        shareForm.reset();
        loadShares();
      } catch (err) {
        alert(err.message);
      }
    });
  }
})();
