import { api, showToast, renderError } from '/js/main.js';

document.addEventListener('DOMContentLoaded', () => {
  const roomForm = document.getElementById('room-form');
  const roomFormResult = document.getElementById('room-form-result');
  const blockForm = document.getElementById('block-form');
  const blockFormResult = document.getElementById('block-form-result');

  if (roomForm) {
    roomForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      roomFormResult.innerHTML = '';
      try {
        const data = await api('/api/hotel/room_inventory', {
          method: 'POST',
          body: JSON.stringify({
            entity: 'room',
            name: roomForm.name.value,
            type: roomForm.type.value,
            price_per_night: Number(roomForm.price_per_night.value),
            capacity: Number(roomForm.capacity.value),
            description: roomForm.description.value,
            amenities: roomForm.amenities.value.split(',').map((s) => s.trim()).filter(Boolean),
          }),
        });
        showToast(data.message, 'success');
        setTimeout(() => window.location.reload(), 700);
      } catch (err) {
        renderError(roomFormResult, err);
      }
    });
  }

  if (blockForm) {
    blockForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      blockFormResult.innerHTML = '';
      try {
        const data = await api('/api/hotel/room_inventory', {
          method: 'POST',
          body: JSON.stringify({
            entity: 'block',
            room_id: Number(blockForm.room_id.value),
            start_date: blockForm.start_date.value,
            end_date: blockForm.end_date.value,
            reason: blockForm.reason.value || 'maintenance',
          }),
        });
        showToast(data.message, 'success');
        setTimeout(() => window.location.reload(), 700);
      } catch (err) {
        renderError(blockFormResult, err);
      }
    });
  }

  document.querySelectorAll('.status-select').forEach((sel) => {
    sel.addEventListener('change', async () => {
      try {
        const data = await api(`/api/hotel/room_inventory/${sel.dataset.id}`, {
          method: 'PATCH',
          body: JSON.stringify({ status: sel.value }),
        });
        showToast(`${data.room.name} → ${data.room.status}`, 'success');
      } catch (err) {
        showToast(err.message, 'error');
      }
    });
  });

  document.querySelectorAll('.remove-block').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!confirm('Remove this availability block?')) return;
      try {
        const data = await api(`/api/hotel/room_inventory/block/${btn.dataset.id}`, { method: 'DELETE' });
        showToast(data.message, 'success');
        setTimeout(() => window.location.reload(), 600);
      } catch (err) {
        showToast(err.message, 'error');
      }
    });
  });
});
