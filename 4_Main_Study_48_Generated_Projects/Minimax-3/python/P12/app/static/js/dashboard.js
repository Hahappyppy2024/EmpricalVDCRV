// Dashboard overview page.
(function () {
  async function loadRecentDatasets() {
    const list = document.querySelector('[data-source="/api/data/dataset_catalog"][data-template="recent-datasets"]');
    if (!list) return;
    try {
      const response = await App.api.get('/api/data/dataset_catalog');
      const items = (response.data.datasets || []).slice(0, 5);
      list.innerHTML = items.map(function (dataset) {
        const tags = (dataset.tags || []).map(function (t) {
          return `<span class="tag-pill">${App.escapeHtml(t)}</span>`;
        }).join('');
        return `<li>
          <strong>${App.escapeHtml(dataset.name)}</strong>
          <small>${App.escapeHtml(dataset.description || '')}</small>
          <small>${tags} ${dataset.row_count} rows · ${dataset.column_count} cols</small>
        </li>`;
      }).join('') || '<li class="muted">No datasets available.</li>';
    } catch (err) {
      list.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  async function loadShares() {
    const list = document.querySelector('[data-source="/api/data/dashboard_sharing"][data-template="recent-shares"]');
    if (!list) return;
    try {
      const response = await App.api.get('/api/data/dashboard_sharing');
      const items = (response.data.shares || []).slice(0, 5);
      list.innerHTML = items.map(function (share) {
        return `<li>
          <strong>${App.escapeHtml(share.description || 'Share ' + share.id)}</strong>
          <small>audience=${App.escapeHtml(share.audience)} permission=${App.escapeHtml(share.permission)}</small>
          <small>revoked=${share.revoked ? 'yes' : 'no'} · expires ${share.expires_at ? App.formatDate(share.expires_at) : 'never'}</small>
        </li>`;
      }).join('') || '<li class="muted">No shares yet.</li>';
    } catch (err) {
      list.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  const profileForm = document.getElementById('profile-form');
  if (profileForm) {
    profileForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const statusNode = profileForm.querySelector('[data-role="status"]');
      const data = {};
      new FormData(profileForm).forEach(function (value, key) { data[key] = value; });
      try {
        await App.api.patch(profileForm.getAttribute('action'), data);
        App.status(statusNode, 'Profile updated.', 'success');
      } catch (err) {
        App.status(statusNode, err.message, 'error');
      }
    });
  }

  loadRecentDatasets();
  loadShares();
})();
