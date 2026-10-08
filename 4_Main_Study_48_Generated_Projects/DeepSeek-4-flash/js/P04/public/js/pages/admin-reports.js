import { api, escapeHtml, money, showToast } from '/js/main.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('report-form');
  const result = document.getElementById('report-result');
  const history = document.getElementById('export-history');

  async function loadHistory() {
    try {
      const data = await api('/api/hotel/admin_reports?type=occupancy');
      renderHistory(data.exports);
    } catch (err) {
      history.innerHTML = '<p class="muted">Unable to load export history.</p>';
    }
  }

  function renderHistory(exports) {
    if (!exports.length) {
      history.innerHTML = '<p class="muted">No report exports yet.</p>';
      return;
    }
    history.innerHTML = `
      <table>
        <tr><th>When</th><th>Type</th><th>Rows</th><th>By</th></tr>
        ${exports.map((e) => `<tr><td>${escapeHtml(e.exported_at)}</td><td>${escapeHtml(e.report_type)}</td><td>${e.row_count}</td><td>${escapeHtml(e.admin_name)}</td></tr>`).join('')}
      </table>`;
  }

  function renderReport(type, data) {
    if (type === 'occupancy') {
      const overall = data.overall_occupancy;
      return `
        <div class="alert alert-info">
          <strong>Occupancy report</strong> (${data.from} → ${data.to})<br>
          Overall occupancy: <strong>${overall}%</strong> · ${data.booked_room_nights}/${data.total_room_nights} room-nights booked
        </div>
        <div class="meter mb-2"><div style="width:${Math.min(overall, 100)}%"></div></div>
        <div class="table-wrap">
          <table>
            <tr><th>Date</th><th>Booked / Total</th><th>Rate</th></tr>
            ${data.daily.map((d) => `<tr><td>${escapeHtml(d.date)}</td><td>${d.booked_rooms}/${d.total_rooms}</td><td>${d.occupancy_rate}%</td></tr>`).join('')}
          </table>
        </div>`;
    }
    if (type === 'revenue') {
      return `
        <div class="alert alert-success">
          <strong>Revenue report</strong> (${data.from} → ${data.to})<br>
          Total revenue: <strong>${money(data.total_revenue)}</strong> across ${data.bookings_count} bookings (avg ${money(data.average_booking_value)})
        </div>
        <div class="table-wrap">
          <table>
            <tr><th>Code</th><th>Dates</th><th>Status</th><th>Total</th></tr>
            ${data.bookings.map((b) => `<tr><td>${escapeHtml(b.code)}</td><td>${b.check_in_date} → ${b.check_out_date}</td><td>${escapeHtml(b.status)}</td><td class="money">${money(b.total_price)}</td></tr>`).join('')}
          </table>
        </div>`;
    }
    return `
      <div class="alert alert-warning" style="background:#fff8e1;color:#b26a00;border-color:#ffecb3;">
        <strong>Cancellation report</strong> (${data.from} → ${data.to})<br>
        Cancelled: <strong>${data.cancelled_count}</strong> bookings worth <strong>${money(data.cancelled_value)}</strong>
      </div>
      <div class="table-wrap">
        <table>
          <tr><th>Code</th><th>Planned stay</th><th>Total</th><th>Cancelled at</th></tr>
          ${data.rows.map((r) => `<tr><td>${escapeHtml(r.code)}</td><td>${r.check_in_date} → ${r.check_out_date}</td><td class="money">${money(r.total_price)}</td><td>${escapeHtml(r.created_at)}</td></tr>`).join('')}
        </table>
      </div>`;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    result.innerHTML = '<p class="muted">Running report…</p>';
    const type = form['report-type'].value;
    const from = form['report-from'].value || '2000-01-01';
    const to = form['report-to'].value || '2100-12-31';
    try {
      const data = await api(`/api/hotel/admin_reports?type=${type}&from=${from}&to=${to}`);
      renderHistory(data.exports);
      result.innerHTML = `
        ${renderReport(type, data.report)}
        <div class="flex mt-2">
          <a class="btn btn-sm" href="/api/hotel/admin_reports?type=${type}&format=csv&from=${from}&to=${to}">Export CSV</a>
          <button class="btn btn-sm btn-primary" id="save-export">Record export</button>
        </div>`;
      document.getElementById('save-export').addEventListener('click', async () => {
        try {
          const outcome = await api('/api/hotel/admin_reports', {
            method: 'POST',
            body: JSON.stringify({ report_type: type, filters: { from, to } }),
          });
          showToast(`Report export recorded (export #${outcome.export_id})`, 'success');
          loadHistory();
        } catch (err) {
          showToast(err.message, 'error');
        }
      });
    } catch (err) {
      result.innerHTML = `<div class="alert alert-error">${escapeHtml(err.message)}</div>`;
    }
  });

  loadHistory();
});
