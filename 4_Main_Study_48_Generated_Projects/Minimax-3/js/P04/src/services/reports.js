import { getDb } from '../db/connection.js';
import { badRequest } from './errors.js';

function dateOnly(d) {
  if (!d) return null;
  return new Date(d + 'T00:00:00Z');
}

export function buildReport({ start_date, end_date, report }) {
  const db = getDb();
  const start = start_date ? new Date(start_date + 'T00:00:00Z').toISOString() : new Date(Date.now() - 30 * 86400000).toISOString();
  const end = end_date ? new Date(end_date + 'T23:59:59Z').toISOString() : new Date().toISOString();

  if (!report) throw badRequest('report is required');
  if (report === 'occupancy') {
    const rows = db.prepare(`
      SELECT r.code, r.name, COUNT(b.id) AS bookings,
        COALESCE(SUM(CASE WHEN b.status='checked_out' THEN
          (julianday(b.check_out) - julianday(b.check_in))
        ELSE 0 END), 0) AS nights_sold
      FROM rooms r LEFT JOIN bookings b ON b.room_id = r.id AND b.status IN ('confirmed','checked_in','checked_out')
        AND b.check_in >= ? AND b.check_out <= ?
      GROUP BY r.id ORDER BY r.code
    `).all(start, end);
    return { report, start, end, rows };
  }
  if (report === 'revenue') {
    const rows = db.prepare(`
      SELECT date(b.check_in) AS day,
        COUNT(b.id) AS bookings,
        COALESCE(SUM(b.total_cents), 0) AS revenue_cents
      FROM bookings b
      WHERE b.status IN ('confirmed','checked_in','checked_out') AND b.check_in >= ? AND b.check_in <= ?
      GROUP BY date(b.check_in) ORDER BY day ASC
    `).all(start, end);
    const total = rows.reduce((acc, r) => acc + Number(r.revenue_cents), 0);
    return { report, start, end, rows, total_cents: total };
  }
  if (report === 'cancellations') {
    const rows = db.prepare(`
      SELECT date(b.updated_at) AS day, COUNT(*) AS cancellations
      FROM bookings b WHERE b.status = 'cancelled' AND b.updated_at >= ? AND b.updated_at <= ?
      GROUP BY date(b.updated_at) ORDER BY day ASC
    `).all(start, end);
    const total = rows.reduce((acc, r) => acc + Number(r.cancellations), 0);
    return { report, start, end, rows, total };
  }
  throw badRequest('Unsupported report type');
}

export function toCsv(reportObj) {
  if (reportObj.report === 'occupancy') {
    const header = 'room_code,room_name,bookings,nights_sold';
    const rows = reportObj.rows.map(r => `${r.code},${JSON.stringify(r.name)},${r.bookings},${r.nights_sold}`);
    return [header, ...rows].join('\n');
  }
  if (reportObj.report === 'revenue') {
    const header = 'day,bookings,revenue_cents,revenue_usd';
    const rows = reportObj.rows.map(r => `${r.day},${r.bookings},${r.revenue_cents},${(r.revenue_cents/100).toFixed(2)}`);
    return [header, ...rows, `# total_cents,${reportObj.total_cents}`].join('\n');
  }
  if (reportObj.report === 'cancellations') {
    const header = 'day,cancellations';
    const rows = reportObj.rows.map(r => `${r.day},${r.cancellations}`);
    return [header, ...rows, `# total,${reportObj.total}`].join('\n');
  }
  return JSON.stringify(reportObj, null, 2);
}