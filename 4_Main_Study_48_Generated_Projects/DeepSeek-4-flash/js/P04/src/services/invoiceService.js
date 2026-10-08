import crypto from 'node:crypto';
import { getDb } from '../db/database.js';
import { AppError } from '../middleware/errors.js';
import { recordAudit } from './auditService.js';

function publicInvoice(row) {
  return {
    id: row.id,
    booking_id: row.booking_id,
    number: row.number,
    booking_code: row.code,
    guest_name: row.guest_name,
    guest_email: row.guest_email,
    room_name: row.room_name,
    check_in_date: row.check_in_date,
    check_out_date: row.check_out_date,
    total: row.total,
    status: row.status,
    issued_at: row.issued_at,
  };
}

const SELECT_INVOICE = `
  SELECT i.*, b.code, b.check_in_date, b.check_out_date, r.name AS room_name, u.name AS guest_name, u.email AS guest_email
  FROM invoices i
  JOIN bookings b ON b.id = i.booking_id
  JOIN rooms r ON r.id = b.room_id
  JOIN users u ON u.id = b.guest_id
`;

export function getInvoice(id) {
  const db = getDb();
  return publicInvoice(db.prepare(`${SELECT_INVOICE} WHERE i.id = ?`).get(id));
}

export function listInvoices(viewer) {
  const db = getDb();
  if (viewer && ['staff', 'admin'].includes(viewer.role)) {
    return db.prepare(`${SELECT_INVOICE} ORDER BY i.issued_at DESC, i.id DESC`).all().map(publicInvoice);
  }
  if (viewer) {
    return db.prepare(`
      ${SELECT_INVOICE} WHERE u.id = ? ORDER BY i.issued_at DESC, i.id DESC
    `).all(viewer.id).map(publicInvoice);
  }
  return [];
}

export function generateInvoice(bookingId, actorId) {
  const db = getDb();
  const booking = db.prepare(`
    SELECT b.*, u.email AS guest_email, r.name AS room_name FROM bookings b
    JOIN users u ON u.id = b.guest_id
    JOIN rooms r ON r.id = b.room_id
    WHERE b.id = ?
  `).get(bookingId);
  if (!booking) throw new AppError(404, 'BOOKING_NOT_FOUND', 'Booking not found.');

  const existing = db.prepare('SELECT * FROM invoices WHERE booking_id = ?').get(bookingId);
  if (existing) return { invoice: publicInvoice(db.prepare(`${SELECT_INVOICE} WHERE i.id = ?`).get(existing.id)), duplicate: true };

  if (booking.status !== 'checked_out') {
    throw new AppError(409, 'INVALID_STATE', `Invoices are generated only for completed stays (current state: ${booking.status}).`);
  }

  const number = `INV-${crypto.randomBytes(6).toString('hex').toUpperCase()}`;
  const info = db.prepare(`
    INSERT INTO invoices (booking_id, number, total, status) VALUES (?, ?, ?, 'issued')
  `).run(bookingId, number, booking.total_price);

  db.prepare(`
    INSERT INTO outbound_notifications (recipient, subject, body) VALUES (?, ?, ?)
  `).run(booking.guest_email, `Invoice ${number} for booking ${booking.code}`, `Thank you for your stay at ${booking.room_name}. Total: $${booking.total_price}.`);

  recordAudit({ actorId, action: 'generate_invoice', targetType: 'booking', targetId: bookingId, details: { number } });
  return { invoice: getInvoice(info.lastInsertRowid), duplicate: false };
}

export function updateInvoiceStatus(invoiceId, status, actorId) {
  const db = getDb();
  if (!['issued', 'paid', 'void'].includes(status)) {
    throw new AppError(400, 'VALIDATION_ERROR', 'Invoice status must be issued, paid, or void.');
  }
  const current = db.prepare('SELECT * FROM invoices WHERE id = ?').get(invoiceId);
  if (!current) throw new AppError(404, 'INVOICE_NOT_FOUND', 'Invoice not found.');
  db.prepare('UPDATE invoices SET status = ? WHERE id = ?').run(status, invoiceId);
  recordAudit({ actorId, action: 'update_invoice', targetType: 'invoice', targetId: invoiceId, details: { status } });
  return getInvoice(invoiceId);
}

function csvEscape(value) {
  const str = value == null ? '' : String(value);
  if (/[",\n]/.test(str)) return `"${str.replace(/"/g, '""')}"`;
  return str;
}

export function invoicesCsv(viewer) {
  const rows = listInvoices(viewer);
  const header = ['Invoice Number', 'Booking Code', 'Guest', 'Email', 'Room', 'Check In', 'Check Out', 'Total', 'Status', 'Issued At'];
  const lines = [header.join(',')];
  for (const r of rows) {
    lines.push([r.number, r.booking_code, r.guest_name, r.guest_email, r.room_name, r.check_in_date, r.check_out_date, r.total, r.status, r.issued_at].map(csvEscape).join(','));
  }
  return lines.join('\n');
}
