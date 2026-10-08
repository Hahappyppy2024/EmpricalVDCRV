import { api, escapeHtml, showToast, renderError } from '/js/main.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('review-form');
  const result = document.getElementById('review-result');

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      result.innerHTML = '';
      const bookingCode = form.booking_code.value.trim().toUpperCase();
      try {
        const bookingsData = await api('/api/hotel/booking_creation');
        const match = bookingsData.bookings.find((b) => b.code.toUpperCase() === bookingCode);
        if (!match) {
          result.innerHTML = '<div class="alert alert-error"><strong>BOOKING_NOT_FOUND</strong> — No booking found with that code for your account.</div>';
          return;
        }
        const data = await api('/api/hotel/reviews', {
          method: 'POST',
          body: JSON.stringify({ booking_id: match.id, rating: Number(form.rating.value), text: form.review_text.value.trim() }),
        });
        showToast(data.message, 'success');
        result.innerHTML = `<div class="alert alert-success">${escapeHtml(data.message)}</div>`;
        form.reset();
      } catch (err) {
        renderError(result, err);
      }
    });
  }

  document.querySelectorAll('.moderate-btn').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        const data = await api(`/api/hotel/reviews/${btn.dataset.id}`, {
          method: 'PATCH',
          body: JSON.stringify({ status: btn.dataset.status }),
        });
        showToast(`Review ${data.review.status}`, 'success');
        setTimeout(() => window.location.reload(), 700);
      } catch (err) {
        showToast(err.message, 'error');
      }
    });
  });
});
