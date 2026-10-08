import { api, escapeHtml, money, showToast } from '/js/main.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('search-form');
  if (!form) return;
  const grid = document.querySelector('.grid');
  const countEl = form.querySelector('.muted');

  const buildCard = (room) => `
    <div class="card">
      <div class="room-media">
        ${room.image_url ? `<img src="${room.image_url}" alt="${escapeHtml(room.name)}" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">` : '<div style="background:#dde6f5;width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#66758a;">' + escapeHtml(room.name) + '</div>'}
      </div>
      <div class="flex-between">
        <h3 style="margin:0;"><a href="/rooms/${room.id}">${escapeHtml(room.name)}</a></h3>
        <span class="money" style="color:var(--primary);">${money(room.price_per_night)}/night</span>
      </div>
      <p class="muted mb-2" style="font-size:13px;">${escapeHtml(room.description)}</p>
      <div class="mb-2">
        <span class="tag">${escapeHtml(room.type)}</span>
        <span class="tag">Up to ${room.capacity} guests</span>
        ${room.amenities.slice(0, 3).map((a) => `<span class="tag">${escapeHtml(a)}</span>`).join('')}
      </div>
      <div class="flex">
        <a href="/rooms/${room.id}" class="btn btn-primary">View details</a>
      </div>
    </div>`;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const qs = new URLSearchParams();
    const fields = ['check_in_date', 'check_out_date', 'capacity', 'min_price', 'max_price', 'type', 'q'];
    for (const f of fields) {
      const v = form[f]?.value;
      if (v) qs.set(f, v);
    }
    try {
      const data = await api(`/api/hotel/room_search?${qs.toString()}`);
      if (countEl) countEl.textContent = `${data.count} matching ${data.count === 1 ? 'room' : 'rooms'}`;
      grid.innerHTML = data.count ? data.results.map(buildCard).join('') : '<div class="alert alert-info" style="grid-column:1/-1;">No rooms match your filters.</div>';
      showToast(`Search recorded (record #${data.record_id})`, 'info');
    } catch (err) {
      showToast(err.message, 'error');
    }
  });
});
