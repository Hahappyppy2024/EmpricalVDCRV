import { Router } from 'express';
import { searchRooms, getRoom, listRooms } from '../services/roomService.js';
import { listBookingsByGuest, listStaffBookings } from '../services/bookingService.js';
import { listReviews } from '../services/reviewService.js';
import { listInvoices } from '../services/invoiceService.js';
import { listForGuest, listForStaff } from '../services/messageService.js';
import { listAvailabilityBlocks } from '../services/roomService.js';
import { listAudit } from '../services/auditService.js';
import { requireAuth } from '../middleware/auth.js';
import { isoDate } from '../db/seed.js';

const router = Router();

function renderPage(req, res, view, locals = {}) {
  res.render(view, { user: req.user, isAuthenticated: req.isAuthenticated, ...locals });
}

function renderErrorPage(req, res, statusCode, code, message, title) {
  res.status(statusCode).render('error', { statusCode, code, message, title, page: 'error', user: req.user, isAuthenticated: req.isAuthenticated });
}

router.get('/', (req, res) => {
  const popular = searchRooms({}).results.slice(0, 6);
  renderPage(req, res, 'home', { page: 'home', popular });
});

router.get('/account', requireAuth, (req, res) => {
  const bookings = listBookingsByGuest(req.user.id);
  renderPage(req, res, 'account', { page: 'account', bookings });
});

router.get('/account/login', (req, res) => {
  if (req.isAuthenticated) return res.redirect('/account');
  renderPage(req, res, 'login', { page: 'login' });
});

router.get('/account/register', (req, res) => {
  if (req.isAuthenticated) return res.redirect('/account');
  renderPage(req, res, 'register', { page: 'register' });
});

router.get('/account/recover', (req, res) => {
  renderPage(req, res, 'recover', { page: 'recover' });
});

router.get('/account/reset', (req, res) => {
  renderPage(req, res, 'reset', { page: 'reset', token: req.query.token || '' });
});

router.get('/search', (req, res) => {
  const filters = {};
  for (const key of ['check_in_date', 'check_out_date', 'capacity', 'min_price', 'max_price', 'type', 'q']) {
    if (req.query[key]) filters[key] = req.query[key];
  }
  const { results, count } = searchRooms(filters);
  renderPage(req, res, 'search', {
    page: 'search',
    filters,
    count,
    results,
    roomTypes: ['single', 'double', 'suite', 'deluxe', 'family'],
    today: isoDate(0),
  });
});

router.get('/rooms/:id', (req, res) => {
  const room = getRoom(Number(req.params.id));
  if (!room) return renderErrorPage(req, res, 404, 'ROOM_NOT_FOUND', 'Room not found.', 'Room not found');
  const availability = searchRooms({ check_in_date: isoDate(0), check_out_date: isoDate(1) }).results.some((r) => r.id === room.id);
  renderPage(req, res, 'roomDetail', { page: 'room', room, availability, today: isoDate(0) });
});

router.get('/book', requireAuth, (req, res) => {
  const rooms = listRooms().filter((r) => r.status === 'active');
  renderPage(req, res, 'book', { page: 'book', rooms, today: isoDate(0), booking: null, roomId: req.query.room_id });
});

router.get('/bookings', requireAuth, (req, res) => {
  const isStaff = ['staff', 'admin'].includes(req.user.role);
  const bookings = isStaff ? listStaffBookings() : listBookingsByGuest(req.user.id);
  const audit = isStaff ? listAudit({ limit: 30 }) : listAudit({ actorId: req.user.id, limit: 30 });
  renderPage(req, res, 'bookings', { page: 'bookings', bookings, audit, isStaff, today: isoDate(0) });
});

router.get('/staff/check-in', requireAuth, (req, res) => {
  if (!['staff', 'admin'].includes(req.user.role)) return renderErrorPage(req, res, 403, 'FORBIDDEN', 'Staff access only.', 'Forbidden');
  renderPage(req, res, 'staffCheckIn', { page: 'staff', today: isoDate(0) });
});

router.get('/staff/inventory', requireAuth, (req, res) => {
  if (!['staff', 'admin'].includes(req.user.role)) return renderErrorPage(req, res, 403, 'FORBIDDEN', 'Staff access only.', 'Forbidden');
  const rooms = listRooms();
  const blocks = listAvailabilityBlocks();
  renderPage(req, res, 'staffInventory', { page: 'inventory', rooms, blocks, today: isoDate(0) });
});

router.get('/messages', requireAuth, (req, res) => {
  const isStaff = ['staff', 'admin', 'moderator'].includes(req.user.role);
  const conversations = isStaff ? listForStaff() : listForGuest(req.user.id);
  renderPage(req, res, 'messages', { page: 'messages', conversations, isStaff });
});

router.get('/reviews', (req, res) => {
  const reviews = listReviews(req.user);
  renderPage(req, res, 'reviews', { page: 'reviews', reviews, today: isoDate(0) });
});

router.get('/invoices', requireAuth, (req, res) => {
  const invoices = listInvoices(req.user);
  renderPage(req, res, 'invoices', { page: 'invoices', invoices, today: isoDate(0) });
});

router.get('/admin/reports', requireAuth, (req, res) => {
  if (req.user.role !== 'admin') return renderErrorPage(req, res, 403, 'FORBIDDEN', 'Admin access only.', 'Forbidden');
  renderPage(req, res, 'adminReports', { page: 'admin', today: isoDate(0) });
});

router.get('/errors', (req, res) => {
  renderPage(req, res, 'errorsDemo', { page: 'errors', today: isoDate(0) });
});

export default router;
