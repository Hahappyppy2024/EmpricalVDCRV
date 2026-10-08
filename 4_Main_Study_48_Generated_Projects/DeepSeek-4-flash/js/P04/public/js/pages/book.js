import { api, escapeHtml, money, showToast, renderError, today } from '/js/main.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('booking-form');
  const result = document.getElementById('booking-result');
  const preview = document.getElementById('price-preview');
  const recent = document.getElementById('recent-bookings');
  if (!form) return;

  const roomsData = JSON.parse(document.getElementById('rooms-data').textContent);

  function selectedRoom() {
    return roomsData.find((r) => r.id === Number(form.room_id.value));
  }

  function updatePreview() {
    const room = selectedRoom();
    const checkIn = form.check_in_date.value;
    const checkOut = form.check_out_date.value;
    if (!room || !checkIn || !checkOut) {
      preview.classList.add('hidden');
      return;
    }
    const nights = (Date.parse(checkOut) - Date.parse(checkIn)) / 86400000;
    if (nights <= 0) {
      preview.classList.remove('hidden');
      preview.textContent = 'Check-out must be after check-in.';
      preview.className = 'alert alert-error';
      return;
    }
    const total = nights * room.price_per_night;
    preview.classList.remove('hidden');
    preview.className = 'alert alert-info';
    preview.innerHTML = `${escapeHtml(room.name)} · ${nights} night${nights === 1 ? '' : 's'} · <strong>${money(total)}</strong> total (${money(room.price_per_night)}/night)`;
  }

  form.room_id.addEventListener('change', updatePreview);
  form.check_in_date.addEventListener('change', updatePreview);
  form.check_out_date.addEventListener('change', updatePreview);
  updatePreview();

  async function loadRecent() {
    try {
      const data = await api('/api/hotel/booking_creation');
      if (!data.bookings.length) {
        recent.innerHTML = '<p class="muted">No reservations yet.</p>';
        return;
      }
      recent.innerHTML = `
        <div class="table-wrap"><table>
          <tr><th>Code</th><th>Room</th><th>Dates</th><th>Status</th></tr>
          ${data.bookings.map((b) => `<tr><td>${escapeHtml(b.code)}</td><td>${escapeHtml(b.room_name)}</td><td>${b.check_in_date} → ${b.check_out_date}</td><td>${`<span class="badge ${b.status}">${b.status}</span>`}</td></tr>`).join('')}
        </table></div>`;
    } catch (err) {
      recent.innerHTML = '<p class="muted">Unable to load reservations.</p>';
    }
  }

  loadRecent();

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    result.innerHTML = '';
    const payload = {
      room_id: Number(form.room_id.value),
      check_in_date: form.check_in_date.value,
      check_out_date: form.check_out_date.value,
      guests: Number(form.guests.value),
      contact_name: form.contact_name.value,
      contact_email: form.contact_email.value,
      contact_phone: form.contact_phone.value,
      card_number: form.card_number.value,
      card_expiry: form.card_expiry.value,
      card_cvc: form.card_cvc.value,
      idempotency_key: form.idempotency_key.value || null,
    };
    try {
      const data = await api('/api/hotel/booking_creation', { method: 'POST', body: JSON.stringify(payload) });
      showToast(data.message, data.duplicate ? 'info' : 'success');
      result.innerHTML = `
        <div class="alert alert-success">
          <strong>${data.duplicate ? 'Existing booking returned' : 'Booking confirmed'}</strong><br>
          Code: <strong>${escapeHtml(data.booking.code)}</strong><br>
          ${escapeHtml(data.booking.room_name)} · ${data.booking.check_in_date} → ${data.booking.check_out_date}<br>
          Total: <strong>${money(data.booking.total_price)}</strong>
          <div class="mt-2"><a class="btn btn-sm" href="/bookings">Manage booking</a></div>
        </div>`;
      loadRecent();
    } catch (err) {
      renderError(result, err);
    }
  });
});
