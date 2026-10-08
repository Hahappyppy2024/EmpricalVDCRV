import { api, showToast } from '/js/main.js';

document.addEventListener('DOMContentLoaded', () => {
  const reload = () => window.location.reload();

  document.querySelectorAll('.cancel-btn').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!confirm('Cancel this booking? This action is audited.')) return;
      try {
        const data = await api('/api/hotel/booking_management', {
          method: 'POST',
          body: JSON.stringify({ action: 'cancel', booking_id: Number(btn.dataset.id) }),
        });
        showToast(`Booking ${data.booking.code} cancelled`, 'success');
        setTimeout(reload, 700);
      } catch (err) {
        showToast(err.message, 'error');
      }
    });
  });

  document.querySelectorAll('.modify-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelector(`.modify-row[data-row-for="${btn.dataset.id}"]`).classList.toggle('hidden');
    });
  });

  document.querySelectorAll('.cancel-modify').forEach((btn) => {
    btn.addEventListener('click', () => btn.closest('.modify-row').classList.add('hidden'));
  });

  document.querySelectorAll('.modify-form').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = form.dataset.id;
      try {
        const data = await api(`/api/hotel/booking_management/${id}`, {
          method: 'PATCH',
          body: JSON.stringify({
            action: 'modify',
            check_in_date: form.check_in_date.value,
            check_out_date: form.check_out_date.value,
            guests: Number(form.guests.value),
          }),
        });
        showToast(`Booking ${data.booking.code} updated`, 'success');
        setTimeout(reload, 700);
      } catch (err) {
        showToast(err.message, 'error');
      }
    });
  });
});
