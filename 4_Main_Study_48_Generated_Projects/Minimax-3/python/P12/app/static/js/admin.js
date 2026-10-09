// Admin operations page.
(function () {
  const statsEl = document.getElementById('admin-stats');
  const limitsEl = document.getElementById('admin-limits');
  const usersEl = document.getElementById('admin-users');
  const settingsForm = document.getElementById('admin-settings-form');
  const purgeForm = document.getElementById('admin-purge-form');

  async function loadOverview() {
    try {
      const response = await App.api.get('/api/data/admin_operations');
      const stats = response.data.stats;
      const limits = response.data.limits;
      statsEl.innerHTML = Object.keys(stats).map(function (key) {
        return `<dt>${App.escapeHtml(key)}</dt><dd>${App.escapeHtml(String(stats[key]))}</dd>`;
      }).join('');
      limitsEl.innerHTML = Object.keys(limits).map(function (key) {
        return `<li>${App.escapeHtml(key)} = ${App.escapeHtml(String(limits[key]))}</li>`;
      }).join('');
    } catch (err) {
      statsEl.innerHTML = `<dd class="error-banner">${App.escapeHtml(err.message)}</dd>`;
    }
  }

  async function loadUsers() {
    try {
      const response = await App.api.get('/api/data/admin_operations/users');
      const users = response.data.users || [];
      usersEl.innerHTML = users.map(function (user) {
        return `<li>
          <strong>${App.escapeHtml(user.username)}</strong>
          <small>${App.escapeHtml(user.email)} · role=${App.escapeHtml(user.role)} · active=${user.is_active}</small>
          <button class="btn" data-role-update="${user.id}" data-role-target="${user.role}">Toggle role</button>
        </li>`;
      }).join('');
      usersEl.querySelectorAll('[data-role-update]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
          const current = btn.getAttribute('data-role-target');
          const next = current === 'admin' ? 'analyst' : (current === 'analyst' ? 'viewer' : 'analyst');
          try {
            await App.api.post('/api/data/admin_operations', {
              action: 'user.role.update',
              user_id: Number(btn.getAttribute('data-role-update')),
              role: next
            });
            loadUsers();
          } catch (err) {
            alert(err.message);
          }
        });
      });
    } catch (err) {
      usersEl.innerHTML = `<li class="error-banner">${App.escapeHtml(err.message)}</li>`;
    }
  }

  if (settingsForm) {
    settingsForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const data = {
        action: 'settings.update',
        key: settingsForm.elements.key.value,
        value: settingsForm.elements.value.value
      };
      try {
        await App.api.post('/api/data/admin_operations', data);
        settingsForm.reset();
        loadOverview();
      } catch (err) {
        alert(err.message);
      }
    });
  }

  if (purgeForm) {
    purgeForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const status = purgeForm.querySelector('[data-role="status"]');
      const days = Number(purgeForm.elements.older_than_days.value);
      try {
        const response = await App.api.post('/api/data/admin_operations', {
          action: 'datasets.purge',
          older_than_days: days
        });
        App.status(status, `Purged ${response.data.purged} datasets older than ${days} days.`, 'success');
      } catch (err) {
        App.status(status, err.message, 'error');
      }
    });
  }

  loadOverview();
  loadUsers();
})();
