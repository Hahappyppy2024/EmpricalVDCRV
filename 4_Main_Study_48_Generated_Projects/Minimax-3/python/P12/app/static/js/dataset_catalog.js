// Dataset catalog page.
(function () {
  const resultsEl = document.getElementById('catalog-results');
  const searchInput = document.getElementById('search-input');
  const tagInput = document.getElementById('tag-input');
  const editForm = document.getElementById('catalog-edit-form');
  const editSelect = document.getElementById('catalog-edit-select');

  async function load() {
    if (!resultsEl) return;
    const params = new URLSearchParams();
    if (searchInput && searchInput.value) params.set('q', searchInput.value);
    if (tagInput && tagInput.value) params.set('tag', tagInput.value);
    const query = params.toString();
    const url = '/api/data/dataset_catalog' + (query ? '?' + query : '');
    try {
      const response = await App.api.get(url);
      renderResults(response.data.datasets || []);
      populateEditOptions(response.data.datasets || []);
    } catch (err) {
      resultsEl.innerHTML = `<div class="error-banner">${App.escapeHtml(err.message)}</div>`;
    }
  }

  function renderResults(datasets) {
    if (!datasets.length) {
      resultsEl.innerHTML = '<p class="muted">No datasets matched your filters.</p>';
      return;
    }
    resultsEl.innerHTML = datasets.map(function (dataset) {
      const tags = (dataset.tags || []).map(function (t) {
        return `<span class="tag-pill">${App.escapeHtml(t)}</span>`;
      }).join('');
      return `<article class="card">
        <header>
          <h2>${App.escapeHtml(dataset.name)}</h2>
          <span class="muted">${App.escapeHtml(dataset.visibility)}</span>
        </header>
        <p class="muted">${App.escapeHtml(dataset.description || '')}</p>
        <p>${tags}</p>
        <p class="muted">${dataset.row_count} rows · ${dataset.column_count} columns</p>
        <p><a class="btn btn-primary" href="/datasets/${dataset.id}">Open</a></p>
      </article>`;
    }).join('');
  }

  function populateEditOptions(datasets) {
    if (!editSelect) return;
    editSelect.innerHTML = datasets.map(function (dataset) {
      return `<option value="${dataset.id}">${App.escapeHtml(dataset.name)}</option>`;
    }).join('');
  }

  if (searchInput) searchInput.addEventListener('input', debounce(load, 250));
  if (tagInput) tagInput.addEventListener('input', debounce(load, 250));

  if (editForm) {
    editForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const statusNode = editForm.querySelector('[data-role="status"]');
      const data = {};
      new FormData(editForm).forEach(function (value, key) { data[key] = value; });
      data.dataset_id = Number(data.dataset_id);
      if (data.tags) data.tags = data.tags.split(',').map(function (t) { return t.trim(); }).filter(Boolean);
      try {
        await App.api.post('/api/data/dataset_catalog', data);
        App.status(statusNode, 'Dataset metadata updated.', 'success');
        load();
      } catch (err) {
        App.status(statusNode, err.message, 'error');
      }
    });
  }

  function debounce(fn, delay) {
    let timer;
    return function () {
      const args = arguments;
      const ctx = this;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
    };
  }

  load();
})();
