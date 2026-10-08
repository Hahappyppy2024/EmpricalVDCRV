import { Router } from 'express';
import { listForGuest, listForStaff, sendMessage, updateMessage } from '../services/messageService.js';
import { requireFields } from '../middleware/validation.js';
import { requireAuth } from '../middleware/auth.js';
import { AppError } from '../middleware/errors.js';

const router = Router();

// GET /api/hotel/guest_messages
router.get('/', requireAuth, (req, res) => {
  const isStaff = ['staff', 'admin', 'moderator'].includes(req.user.role);
  const conversations = isStaff ? listForStaff() : listForGuest(req.user.id);
  res.json({ role: req.user.role, conversations });
});

// POST /api/hotel/guest_messages
router.post('/', requireAuth, (req, res) => {
  requireFields(req.body, ['booking_id', 'body']);
  const message = sendMessage({
    booking_id: Number(req.body.booking_id),
    user_id: req.user.id,
    role: req.user.role,
    body: String(req.body.body).trim(),
  });
  res.status(201).json({ message });
});

// PATCH /api/hotel/guest_messages/:id
router.patch('/:id', requireAuth, (req, res) => {
  requireFields(req.body, ['body']);
  const message = updateMessage(Number(req.params.id), req.user.id, String(req.body.body).trim());
  res.json({ message, ok: true });
});

export default router;
