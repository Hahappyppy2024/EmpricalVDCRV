import { Router } from 'express';
import { listInvoices, generateInvoice, updateInvoiceStatus, invoicesCsv, getInvoice } from '../services/invoiceService.js';
import { requireFields } from '../middleware/validation.js';
import { requireAuth } from '../middleware/auth.js';
import { AppError } from '../middleware/errors.js';

const router = Router();

// GET /api/hotel/invoice_and_receipt
router.get('/', requireAuth, (req, res) => {
  if (req.query.format === 'csv') {
    res.setHeader('Content-Type', 'text/csv; charset=utf-8');
    res.setHeader('Content-Disposition', 'attachment; filename="invoices.csv"');
    return res.send(invoicesCsv(req.user));
  }
  res.json({ invoices: listInvoices(req.user) });
});

// POST /api/hotel/invoice_and_receipt
router.post('/', requireAuth, (req, res) => {
  requireFields(req.body, ['booking_id']);
  const bookingId = Number(req.body.booking_id);
  const outcome = generateInvoice(bookingId, req.user.id);
  res.status(outcome.duplicate ? 200 : 201).json({ ...outcome, message: outcome.duplicate ? 'Invoice already exists for this booking.' : 'Invoice generated.' });
});

// PATCH /api/hotel/invoice_and_receipt/:id  (staff mark paid/void)
router.patch('/:id', requireAuth, (req, res) => {
  if (!['staff', 'admin'].includes(req.user.role)) {
    throw new AppError(403, 'FORBIDDEN', 'Only staff can update invoice status.');
  }
  requireFields(req.body, ['status']);
  const invoice = updateInvoiceStatus(Number(req.params.id), req.body.status, req.user.id);
  res.json({ invoice, message: 'Invoice status updated.' });
});

export default router;
