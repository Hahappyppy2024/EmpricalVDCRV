import { api, escapeHtml, showToast, wsConnect, setWsStatus } from '/js/main.js';

document.addEventListener('DOMContentLoaded', () => {
  const checkinBody = document.querySelector('#checkin-table tbody');
  const checkoutBody = document.querySelector('#checkout-table tbody');
  const eventsBody = document.querySelector('#events-table tbody');

  const bookingRow = (b, actionLabel, actionFn) => `
    <tr data-booking-id="${b.id}">
      <td>${escapeHtml(b.code)}</td>
      <td>${escapeHtml(b.guest_name)}</td>
      <td>${escapeHtml(b.room_name)}</td>
      <td>${b.check_in_date} → ${b.check_out_date}</td>
      <td><span class="badge ${b.status}">${b.status}</span></td>
      <td><button class="btn btn-sm btn-primary action-btn" data-id="${b.id}">${actionLabel}</button></td>
    </tr>`;

  async function load() {
    try {
      const data = await api('/api/hotel/staff_check_in_out');
      checkinBody.innerHTML = data.due_for_check_in.length
        ? data.due_for_check_in.map((b) => bookingRow(b, 'Check in', 'check_in')).join('')
        : '<tr><td colspan="6" class="muted">No bookings due for check-in.</td></tr>';
      checkoutBody.innerHTML = data.due_for_check_out.length
        ? data.due_for_check_out.map((b) => bookingRow(b, 'Check out', 'check_out')).join('')
        : '<tr><td colspan="6" class="muted">No guests currently checked in.</td></tr>';
      eventsBody.innerHTML = data.events.length
        ? data.events.map((e) => `
            <tr>
              <td>${escapeHtml(e.created_at)}</td>
              <td>${escapeHtml(e.code)} (${escapeHtml(e.booking_status)})</td>
              <td>${escapeHtml(e.action)}</td>
              <td>${escapeHtml(e.actor_name)}</td>
            </tr>`).join('')
        : '<tr><td colspan="4" class="muted">No events yet.</td></tr>';

      document.querySelectorAll('.action-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const action = btn.textContent.toLowerCase().includes('in') ? 'check_in' : 'check_out';
          btn.disabled = true;
          try {
            const data = await api('/api/hotel/staff_check_in_out', {
              method: 'POST',
              body: JSON.stringify({ booking_id: Number(btn.dataset.id), action }),
            });
            showToast(data.message, 'success');
            load();
          } catch (err) {
            btn.disabled = false;
            showToast(err.message, 'error');
          }
        });
      });
    } catch (err) {
      checkinBody.innerHTML = `<tr><td colspan="6" class="muted">${escapeHtml(err.message)}</td></tr>`;
      showToast(err.message, 'error');
    }
  }

  const ws = wsConnect(() => {
    // Realtime event pushed to all connected clients: refresh the lists.
    load();
  });
  setWsStatus(ws);

  load();
});
