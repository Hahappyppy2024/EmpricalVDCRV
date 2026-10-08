import { getDb } from '../db/database.js';
import { recordAudit } from './auditService.js';
import { AppError } from '../middleware/errors.js';

function eachDate(from, to) {
  const dates = [];
  const start = new Date(from + 'T00:00:00Z');
  const end = new Date(to + 'T00:00:00Z');
  for (let d = new Date(start); d <= end; d.setUTCDate(d.getUTCDate() + 1)) {
    dates.push(d.toISOString().slice(0, 10));
  }
  return dates;
}

export function occupancyReport(from, to) {
  const db = getDb();
  const totalRooms = db.prepare("SELECT COUNT(*) AS n FROM rooms WHERE status = 'active'").get().n;
  const days = eachDate(from, to);
  const rows = days.map((date) => {
    const booked = db.prepare(`
      SELECT COUNT(DISTINCT room_id) AS n FROM bookings
      WHERE status != 'cancelled' AND check_in_date <= ? AND check_out_date > ?
    `).get(date, date).n;
    return {
      date,
      total_rooms: totalRooms,
      booked_rooms: booked,
      occupancy_rate: totalRooms === 0 ? 0 : Math.round((booked / totalRooms) * 1000) / 10,
    };
  });
  const bookedNights = rows.reduce((acc, r) => acc + r.booked_rooms, 0);
  const roomNights = totalRooms * days.length;
  return {
    from,
    to,
    total_rooms: totalRooms,
    total_room_nights: roomNights,
    booked_room_nights: bookedNights,
    overall_occupancy: roomNights === 0 ? 0 : Math.round((bookedNights / roomNights) * 1000) / 10,
    daily: rows,
  };
}

export function revenueReport(from, to) {
  const db = getDb();
  const rows = db.prepare(`
    SELECT * FROM bookings
    WHERE check_in_date BETWEEN ? AND ?
      AND status IN ('confirmed', 'checked_in', 'checked_out')
    ORDER BY check_in_date ASC
  `).all(from, to);
  const totalRevenue = rows.reduce((acc, r) => acc + r.total_price, 0);
  const counts = rows.reduce((acc, r) => {
    acc[r.status] = (acc[r.status] || 0) + 1;
    return acc;
  }, {});
  return {
    from,
    to,
    bookings_count: rows.length,
    total_revenue: totalRevenue,
    average_booking_value: rows.length ? Math.round(totalRevenue / rows.length) : 0,
    breakdown: counts,
    bookings: rows.map((r) => ({ code: r.code, room_id: r.room_id, check_in_date: r.check_in_date, check_out_date: r.check_out_date, status: r.status, total_price: r.total_price })),
  };
}

export function cancellationReport(from, to) {
  const db = getDb();
  const rows = db.prepare(`
    SELECT * FROM bookings
    WHERE status = 'cancelled' AND created_at BETWEEN ? || ' 00:00:00' AND ? || ' 23:59:59'
    ORDER BY created_at DESC
  `).all(from, to);
  const totalValue = rows.reduce((acc, r) => acc + r.total_price, 0);
  return {
    from,
    to,
    cancelled_count: rows.length,
    cancelled_value: totalValue,
    cancellation_rate: null,
    rows: rows.map((r) => ({ code: r.code, room_id: r.room_id, check_in_date: r.check_in_date, check_out_date: r.check_out_date, total_price: r.total_price, created_at: r.created_at })),
  };
}

export function runReport(reportType, filters = {}) {
  const from = filters.from || filters.start_date || '2000-01-01';
  const to = filters.to || filters.end_date || '2100-12-31';
  if (!/^\d{4}-\d{2}-\d{2}$/.test(from) || !/^\d{4}-\d{2}-\d{2}$/.test(to) || to < from) {
    throw new AppError(400, 'VALIDATION_ERROR', 'A valid date range is required for the report.');
  }
  switch (reportType) {
    case 'occupancy':
      return occupancyReport(from, to);
    case 'revenue':
      return revenueReport(from, to);
    case 'cancellations':
      return cancellationReport(from, to);
    default:
      throw new AppError(400, 'VALIDATION_ERROR', 'Report type must be occupancy, revenue, or cancellations.');
  }
}

export function generateReport({ adminId, reportType, filters }) {
  const data = runReport(reportType, filters);
  const db = getDb();
  const rowCount = data.daily ? data.daily.length : data.bookings_count ?? data.cancelled_count ?? 0;
  const info = db.prepare(`
    INSERT INTO report_exports (admin_id, report_type, filters, row_count)
    VALUES (?, ?, ?, ?)
  `).run(adminId, reportType, JSON.stringify(filters || {}), rowCount);
  recordAudit({ actorId: adminId, action: 'report_export', targetType: 'report', targetId: info.lastInsertRowid, details: { reportType, filters } });
  return { report: data, export_id: info.lastInsertRowid, report_type: reportType };
}

function csvEscape(value) {
  const str = value == null ? '' : String(value);
  if (/[",\n]/.test(str)) return `"${str.replace(/"/g, '""')}"`;
  return str;
}

export function reportCsv(reportType, data) {
  const lines = [];
  if (reportType === 'occupancy') {
    lines.push(['date', 'total_rooms', 'booked_rooms', 'occupancy_rate'].join(','));
    for (const r of data.daily) {
      lines.push([r.date, r.total_rooms, r.booked_rooms, r.occupancy_rate].join(','));
    }
  } else if (reportType === 'revenue') {
    lines.push(['code', 'room_id', 'check_in_date', 'check_out_date', 'status', 'total_price'].join(','));
    for (const r of data.bookings) {
      lines.push([r.code, r.room_id, r.check_in_date, r.check_out_date, r.status, r.total_price].map(csvEscape).join(','));
    }
  } else {
    lines.push(['code', 'room_id', 'check_in_date', 'check_out_date', 'total_price', 'created_at'].join(','));
    for (const r of data.rows) {
      lines.push([r.code, r.room_id, r.check_in_date, r.check_out_date, r.total_price, r.created_at].map(csvEscape).join(','));
    }
  }
  return lines.join('\n');
}

export function listReportExports() {
  const db = getDb();
  return db.prepare(`
    SELECT e.*, u.name AS admin_name FROM report_exports e
    JOIN users u ON u.id = e.admin_id
    ORDER BY e.exported_at DESC, e.id DESC
    LIMIT 100
  `).all();
}
