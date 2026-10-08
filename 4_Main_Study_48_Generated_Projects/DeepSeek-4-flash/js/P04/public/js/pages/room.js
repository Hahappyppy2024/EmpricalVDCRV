import { api, escapeHtml, showToast } from '/js/main.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('availability-form');
  const result = document.getElementById('availability-result');
  if (!form || !result) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    result.innerHTML = '';
    const checkIn = form['avail-in'].value;
    const checkOut = form['avail-out'].value;
    if (!checkIn || !checkOut) {
      result.innerHTML = '<div class="alert alert-error"><strong>VALIDATION_ERROR</strong> — Both dates are required.</div>';
      return;
    }
    if (checkOut <= checkIn) {
      result.innerHTML = '<div class="alert alert-error"><strong>VALIDATION_ERROR</strong> — Check-out must be after check-in.</div>';
      return;
    }
    const roomId = window.location.pathname.split('/').pop();
    try {
      const data = await api(`/api/hotel/room_details/${roomId}?check_in_date=${checkIn}&check_out_date=${checkOut}`);
      const available = data.availability.available;
      result.innerHTML = available
        ? `<div class="alert alert-success"><strong>Available</strong> — ${escapeHtml(data.room.name)} is bookable for these dates.</div>`
        : `<div class="alert alert-error"><strong>Unavailable</strong> — ${escapeHtml(data.room.name)} is already booked or blocked for these dates.</div>`;
    } catch (err) {
      result.innerHTML = `<div class="alert alert-error"><strong>${escapeHtml(err.code || 'ERROR')}</strong> — ${escapeHtml(err.message)}</div>`;
      showToast(err.message, 'error');
    }
  });
});
