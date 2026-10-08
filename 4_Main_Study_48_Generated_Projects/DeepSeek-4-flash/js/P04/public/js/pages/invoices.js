import { api, escapeHtml, money, showToast, renderError } from '/js/main.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('invoice-form');
  const result = document.getElementById('invoice-result');
  const receipt = document.getElementById('receipt');

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      result.innerHTML = '';
      const code = form.invoice_code.value.trim().toUpperCase();
      try {
        const data = await api('/api/hotel/booking_management');
        const match = data.bookings.find((b) => b.code.toUpperCase() === code);
        if (!match) {
          result.innerHTML = '<div class="alert alert-error"><strong>BOOKING_NOT_FOUND</strong> — No booking found with that code.</div>';
          return;
        }
        const inv = await api('/api/hotel/invoice_and_receipt', {
          method: 'POST',
          body: JSON.stringify({ booking_id: match.id }),
        });
        result.innerHTML = `<div class="alert alert-success"><strong>${inv.message}</strong> — Invoice ${escapeHtml(inv.invoice.number)} · ${money(inv.invoice.total)}</div>`;
        showToast(inv.message, 'success');
        setTimeout(() => window.location.reload(), 900);
      } catch (err) {
        renderError(result, err);
      }
    });
  }

  document.querySelectorAll('.print-receipt').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        const data = await api('/api/hotel/invoice_and_receipt');
        const inv = data.invoices.find((i) => i.id === Number(btn.dataset.id));
        if (!inv) return;
        renderReceipt(inv);
        receipt.style.display = 'block';
        window.print();
      } catch (err) {
        showToast(err.message, 'error');
      }
    });
  });

  function renderReceipt(inv) {
    const nights = Math.round((Date.parse(inv.check_out_date) - Date.parse(inv.check_in_date)) / 86400000);
    receipt.innerHTML = `
      <h3>Official receipt</h3>
      <div style="border-bottom:2px solid var(--text);padding-bottom:8px;margin-bottom:12px;">
        <strong>Meridian Grand Hotel</strong><br>
        <span class="muted">123 Seaside Avenue · +1 555 0199 · hello@hotel.test</span>
      </div>
      <table>
        <tr><th>Invoice number</th><td>${escapeHtml(inv.number)}</td></tr>
        <tr><th>Booking</th><td>${escapeHtml(inv.booking_code)}</td></tr>
        <tr><th>Guest</th><td>${escapeHtml(inv.guest_name)} (${escapeHtml(inv.guest_email)})</td></tr>
        <tr><th>Room</th><td>${escapeHtml(inv.room_name)}</td></tr>
        <tr><th>Stay</th><td>${inv.check_in_date} → ${inv.check_out_date} (${nights} night${nights === 1 ? '' : 's'})</td></tr>
        <tr><th>Status</th><td>${escapeHtml(inv.status)}</td></tr>
        <tr><th>Issued</th><td>${escapeHtml(inv.issued_at)}</td></tr>
        <tr><th>Total</th><td class="money">${money(inv.total)}</td></tr>
      </table>
      <p class="muted" style="font-size:13px;margin-top:12px;">Thank you for staying with us.</p>
    `;
  }
});
