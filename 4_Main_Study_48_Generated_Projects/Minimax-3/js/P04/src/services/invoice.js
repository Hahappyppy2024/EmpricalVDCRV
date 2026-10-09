import crypto from 'node:crypto';
import { getDb } from '../db/connection.js';
import { badRequest, forbidden, notFound } from './errors.js';
import { findBookingById } from './bookings.js';

function uniqueNumber(prefix) {
  return prefix + '-' + crypto.randomBytes(4).toString('hex').toUpperCase();
}

export function getOrCreateInvoice(actor, bookingId) {
  const db = getDb();
  const booking = findBookingById(bookingId);
  if (!booking) throw notFound('Booking not found');
  if (actor.role === 'guest' && booking.user_id !== actor.id) throw forbidden('Cannot view invoice for another guest');
  const existing = db.prepare('SELECT * FROM invoices WHERE booking_id = ?').get(bookingId);
  if (existing) return existing;
  const tax = Math.round(booking.total_cents * 0.08);
  const info = db.prepare(`INSERT INTO invoices (booking_id, number, total_cents, tax_cents, status) VALUES (?, ?, ?, ?, ?)`)
    .run(bookingId, uniqueNumber('INV'), booking.total_cents, tax, booking.payment_status === 'paid' ? 'paid' : 'issued');
  return db.prepare('SELECT * FROM invoices WHERE id = ?').get(info.lastInsertRowid);
}

export function getOrCreateReceipt(actor, bookingId) {
  const db = getDb();
  const booking = findBookingById(bookingId);
  if (!booking) throw notFound('Booking not found');
  if (actor.role === 'guest' && booking.user_id !== actor.id) throw forbidden('Cannot view receipt for another guest');
  if (booking.payment_status !== 'paid') throw badRequest('Booking is not paid; receipt not available');
  const existing = db.prepare('SELECT * FROM receipts WHERE booking_id = ?').get(bookingId);
  if (existing) return existing;
  const method = 'card-' + (booking.card_last4 || '0000');
  const info = db.prepare(`INSERT INTO receipts (booking_id, number, amount_cents, method) VALUES (?, ?, ?, ?)`)
    .run(bookingId, uniqueNumber('RCPT'), booking.total_cents, method);
  return db.prepare('SELECT * FROM receipts WHERE id = ?').get(info.lastInsertRowid);
}

export function invoiceHtml(invoice, booking, room, user, hotelName) {
  const lines = [
    `<html><head><meta charset="utf-8"><title>Invoice ${invoice.number}</title>`,
    `<style>body{font-family:Arial,sans-serif;max-width:640px;margin:32px auto;color:#222}table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid #eee;text-align:left}h1{color:#0a4d8c}</style>`,
    `</head><body>`,
    `<h1>${hotelName}</h1>`,
    `<p><strong>Invoice:</strong> ${invoice.number}<br><strong>Issued:</strong> ${invoice.issued_at}<br><strong>Status:</strong> ${invoice.status}</p>`,
    `<p><strong>Guest:</strong> ${user.display_name} &lt;${user.email}&gt;<br><strong>Booking:</strong> ${booking.code}<br><strong>Room:</strong> ${room.name} (${room.code})<br><strong>Stay:</strong> ${booking.check_in} to ${booking.check_out}</p>`,
    `<table><tr><th>Description</th><th style="text-align:right">Amount</th></tr>`,
    `<tr><td>Room charges (${booking.check_in} to ${booking.check_out})</td><td style="text-align:right">$${(booking.total_cents/100).toFixed(2)}</td></tr>`,
    `<tr><td>Tax</td><td style="text-align:right">$${(invoice.tax_cents/100).toFixed(2)}</td></tr>`,
    `<tr><th>Total</th><th style="text-align:right">$${((booking.total_cents + invoice.tax_cents)/100).toFixed(2)}</th></tr></table>`,
    `<p style="margin-top:32px">Generated locally by HBS benchmark system.</p>`,
    `</body></html>`,
  ];
  return lines.join('');
}