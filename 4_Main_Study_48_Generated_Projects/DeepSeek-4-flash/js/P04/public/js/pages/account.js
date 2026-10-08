import { api, escapeHtml, showToast, renderError } from '/js/main.js';

document.addEventListener('DOMContentLoaded', () => {
  const sessionsBox = document.getElementById('sessions-list');
  const accessBox = document.getElementById('access-log');

  async function load() {
    try {
      const data = await api('/api/hotel/account_access');
      renderSessions(data.sessions, data.current_session_id);
      renderAccessLog(data.access_log);
    } catch (err) {
      if (sessionsBox) sessionsBox.innerHTML = '<p class="muted">Unable to load sessions.</p>';
      if (accessBox) accessBox.innerHTML = '<p class="muted">Unable to load activity.</p>';
    }
  }

  function renderSessions(sessions, currentId) {
    if (!sessionsBox) return;
    if (!sessions.length) {
      sessionsBox.innerHTML = '<p class="muted">No active sessions.</p>';
      return;
    }
    sessionsBox.innerHTML = `
      <table>
        <tr><th>Session</th><th>Created</th><th>Expires</th><th>Action</th></tr>
        ${sessions.map((s) => `
          <tr>
            <td>${s.current ? '<strong>This session</strong>' : escapeHtml(s.id)}</td>
            <td>${escapeHtml(s.created_at)}</td>
            <td>${escapeHtml(s.expires_at)}</td>
            <td>${s.current ? '' : `<button class="btn btn-sm btn-danger revoke-btn" data-id="${s.id}">Revoke</button>`}</td>
          </tr>`).join('')}
      </table>`;
    sessionsBox.querySelectorAll('.revoke-btn').forEach((btn) => {
      btn.addEventListener('click', async () => {
        try {
          await api(`/api/hotel/account_access/${btn.dataset.id}`, { method: 'PATCH', body: JSON.stringify({ action: 'revoke_session' }) });
          showToast('Session revoked', 'success');
          load();
        } catch (err) { showToast(err.message, 'error'); }
      });
    });
  }

  function renderAccessLog(entries) {
    if (!accessBox) return;
    if (!entries.length) {
      accessBox.innerHTML = '<p class="muted">No account activity yet.</p>';
      return;
    }
    accessBox.innerHTML = `
      <table>
        <tr><th>When</th><th>Action</th><th>Details</th></tr>
        ${entries.slice(0, 12).map((e) => `
          <tr><td>${escapeHtml(e.created_at)}</td><td>${escapeHtml(e.action)}</td><td>${escapeHtml(e.details || '')}</td></tr>`).join('')}
      </table>`;
  }

  load();

  const editBtn = document.getElementById('edit-profile-btn');
  const profileForm = document.getElementById('profile-form');
  const profileResult = document.getElementById('profile-result');
  if (editBtn && profileForm) {
    editBtn.addEventListener('click', () => {
      profileForm.classList.toggle('hidden');
      editBtn.classList.toggle('hidden');
    });
    document.getElementById('cancel-profile').addEventListener('click', () => {
      profileForm.classList.add('hidden');
      editBtn.classList.remove('hidden');
    });
    profileForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      profileResult.innerHTML = '';
      try {
        const data = await api('/api/hotel/account_access/' + window.__userId, { method: 'PATCH', body: JSON.stringify({ action: 'update_profile', name: document.getElementById('profile-name').value, phone: document.getElementById('profile-phone').value }) });
        showToast('Profile updated', 'success');
        profileResult.innerHTML = `<div class="alert alert-success">Profile updated.</div>`;
        setTimeout(() => window.location.reload(), 1000);
      } catch (err) { renderError(profileResult, err); }
    });
  }
});
