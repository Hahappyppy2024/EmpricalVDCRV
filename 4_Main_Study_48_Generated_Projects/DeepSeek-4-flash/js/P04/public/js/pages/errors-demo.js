import { api, escapeHtml, money, showToast, addDays } from '/js/main.js';

document.addEventListener('DOMContentLoaded', () => {
  const simResult = document.getElementById('sim-result');

  document.querySelectorAll('.sim-btn').forEach((btn) => {
    btn.addEventListener('click', async () => {
      simResult.innerHTML = '<p class="muted">Running scenario…</p>';
      try {
        const data = await api('/api/hotel/frontend_api_integration_and_errors', {
          method: 'POST',
          body: JSON.stringify({ action: 'simulate', scenario: btn.dataset.scenario }),
        });
        renderSim(data);
      } catch (err) {
        simResult.innerHTML = `<div class="alert alert-error"><strong>${escapeHtml(err.code)}</strong> — ${escapeHtml(err.message)}</div>`;
      }
    });
  });

  function renderSim(data) {
    const s = data.simulated;
    simResult.innerHTML = `
      <div class="alert alert-info">
        <strong>Scenario: ${escapeHtml(data.scenario)}</strong>
        <div style="margin-top:6px;">
          <strong>${escapeHtml(s.message)}</strong>
          ${s.available !== undefined ? `<div>Available for requested dates: <strong>${s.available ? 'yes' : 'no'}</strong></div>` : ''}
          ${s.conflict_room ? `<div>Conflict with: <strong>${escapeHtml(s.conflict_room)}</strong></div>` : ''}
          ${s.code ? `<div>API code: <strong>${escapeHtml(s.code)}</strong> (HTTP ${s.status})</div>` : ''}
          ${s.kind ? `<div class="muted" style="font-size:12px;">kind: ${escapeHtml(s.kind)}</div>` : ''}
        </div>
        <div class="muted" style="font-size:12px;margin-top:6px;">Recorded as error report #${data.record_id}</div>
      </div>`;
    loadReports();
  }

  async function loadReports() {
    const box = document.getElementById('error-reports');
    try {
      const data = await api('/api/hotel/frontend_api_integration_and_errors');
      box.innerHTML = data.reports.length
        ? `<table><tr><th>When</th><th>Context</th><th>Payload</th></tr>
            ${data.reports.slice(0, 12).map((r) => `<tr><td>${escapeHtml(r.created_at)}</td><td>${escapeHtml(r.context)}</td><td style="font-size:12px;">${escapeHtml(r.payload || '')}</td></tr>`).join('')}
          </table>`
        : '<p class="muted">No error reports yet.</p>';
    } catch (err) {
      box.innerHTML = '<p class="muted">Unable to load reports.</p>';
    }
  }

  const reportForm = document.getElementById('error-report-form');
  if (reportForm) {
    reportForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const resultBox = document.getElementById('error-report-result');
      try {
        let payload = {};
        try { payload = JSON.parse(reportForm.payload.value || '{}'); } catch { payload = { raw: reportForm.payload.value }; }
        await api('/api/hotel/frontend_api_integration_and_errors', {
          method: 'POST',
          body: JSON.stringify({ context: reportForm.context.value, payload }),
        });
        showToast('Error report recorded', 'success');
        reportForm.reset();
        loadReports();
      } catch (err) {
        resultBox.innerHTML = `<div class="alert alert-error">${escapeHtml(err.message)}</div>`;
      }
    });
  }

  async function buildLiveDemo() {
    const box = document.getElementById('live-error-demo');
    try {
      const data = await api('/api/hotel/room_details');
      const suite = data.rooms.find((r) => r.name.includes('R301') || r.type === 'suite');
      if (!suite) {
        box.innerHTML = '<p class="muted">Suite room not found.</p>';
        return;
      }
      const checkIn = addDays(new Date().toISOString().slice(0, 10), 16);
      const checkOut = addDays(new Date().toISOString().slice(0, 10), 18);
      box.innerHTML = `
        <p class="muted mb-2">Room: <strong>${escapeHtml(suite.name)}</strong> · requested ${checkIn} → ${checkOut} (inside the seeded maintenance block)</p>
        <button class="btn btn-sm" id="attempt-booking">Attempt booking (expect 409)</button>
        <div id="attempt-result" style="margin-top:10px;"></div>`;
      document.getElementById('attempt-booking').addEventListener('click', async () => {
        const out = document.getElementById('attempt-result');
        out.innerHTML = '<p class="muted">Calling POST /api/hotel/booking_creation…</p>';
        try {
          const created = await api('/api/hotel/booking_creation', {
            method: 'POST',
            body: JSON.stringify({
              room_id: suite.id,
              check_in_date: checkIn,
              check_out_date: checkOut,
              guests: 1,
              contact_name: 'Frontend Demo',
              contact_email: 'demo@hotel.test',
              contact_phone: '',
              card_number: '4242424242424242',
              card_expiry: '12/29',
              card_cvc: '123',
            }),
          });
          out.innerHTML = `<div class="alert alert-success">Unexpected: booking ${escapeHtml(created.booking.code)} was created.</div>`;
        } catch (err) {
          out.innerHTML = `
            <div class="alert alert-error">
              <strong>${escapeHtml(err.code)}</strong> (HTTP ${err.status}) — ${escapeHtml(err.message)}
            </div>
            <p class="muted" style="font-size:12px;">The UI displayed a deterministic, non-stack-trace error to the user and kept the form state intact.</p>`;
          showToast(`${err.code}: ${err.message}`, 'error');
        }
      });
    } catch (err) {
      box.innerHTML = `<div class="alert alert-error">${escapeHtml(err.message)}</div>`;
    }
  }

  loadReports();
  buildLiveDemo();
});
