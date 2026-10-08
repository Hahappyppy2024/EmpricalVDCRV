import { api, escapeHtml, showToast, wsConnect, setWsStatus } from '/js/main.js';

document.addEventListener('DOMContentLoaded', () => {
  const addBubble = (bookingId, message) => {
    const card = document.querySelector(`.card[data-booking-id="${bookingId}"]`);
    if (!card) return;
    const box = card.querySelector('.chat-box');
    const empty = box.querySelector('.muted');
    if (empty) empty.remove();
    const div = document.createElement('div');
    div.className = `msg-bubble ${message.sender_role}`;
    div.innerHTML = `<div>${escapeHtml(message.body)}</div><div class="msg-meta">${escapeHtml(message.author_name)} · now</div>`;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
  };

  document.querySelectorAll('.msg-form').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const bookingId = Number(form.dataset.bookingId);
      const textarea = form.querySelector('textarea');
      const body = textarea.value.trim();
      if (!body) return;
      textarea.value = '';
      try {
        const data = await api('/api/hotel/guest_messages', {
          method: 'POST',
          body: JSON.stringify({ booking_id: bookingId, body }),
        });
        addBubble(bookingId, data.message);
        showToast('Message sent', 'success');
      } catch (err) {
        textarea.value = body;
        showToast(err.message, 'error');
      }
    });
  });

  const ws = wsConnect((data) => {
    if (data.type === 'message.created') {
      addBubble(data.payload.booking_id, {
        body: data.payload.body,
        author_name: data.payload.author_name,
        sender_role: data.payload.sender_role,
      });
    }
  });
  setWsStatus(ws);
});
