import { Router } from 'express';
import { register, login, logout, requestPasswordReset, confirmPasswordReset } from '../services/authService.js';
import { listSessionsForUser, revokeSessionById } from '../services/sessionService.js';
import { requireFields, isValidEmail } from '../middleware/validation.js';
import { setSessionCookie, clearSessionCookie } from '../middleware/validation.js';
import { requireAuth } from '../middleware/auth.js';
import { AppError } from '../middleware/errors.js';
import { getDb } from '../db/database.js';
import { config } from '../config.js';

const SESSION_DAYS = 30;

const router = Router();

// GET /api/hotel/account_access
router.get('/', requireAuth, (req, res) => {
  const db = getDb();
  const accessLog = db.prepare(`
    SELECT l.*, u.name AS user_name FROM account_access_log l
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.created_at DESC, l.id DESC LIMIT 50
  `).all();
  const sessions = listSessionsForUser(req.user.id).map((s) => ({
    id: s.id,
    current: s.id === req.user.session_id,
    created_at: s.created_at,
    expires_at: s.expires_at,
    revoked_at: s.revoked_at,
  }));
  res.json({
    user: req.user,
    current_session_id: req.user.session_id,
    sessions,
    access_log: accessLog,
  });
});

// POST /api/hotel/account_access
router.post('/', (req, res) => {
  const { action } = req.body;
  requireFields(req.body, ['action']);

  switch (action) {
    case 'register': {
      requireFields(req.body, ['name', 'email', 'password']);
      if (!isValidEmail(req.body.email)) throw new AppError(400, 'VALIDATION_ERROR', 'A valid email address is required.');
      if (req.body.password.length < 8) throw new AppError(400, 'VALIDATION_ERROR', 'Password must be at least 8 characters.');
      const { token, user } = register({
        name: req.body.name, email: req.body.email, password: req.body.password, phone: req.body.phone,
      });
      setSessionCookie(res, token, SESSION_DAYS * 24 * 3600);
      return res.status(201).json({ user, redirect: '/account' });
    }
    case 'login': {
      requireFields(req.body, ['email', 'password']);
      const { token, user } = login({ email: req.body.email, password: req.body.password });
      setSessionCookie(res, token, SESSION_DAYS * 24 * 3600);
      return res.json({ user, redirect: '/account' });
    }
    case 'logout': {
      if (req.isAuthenticated) logout(req.session.token);
      clearSessionCookie(res);
      return res.json({ ok: true, redirect: '/' });
    }
    case 'reset': {
      requireFields(req.body, ['email']);
      const outcome = requestPasswordReset({ email: req.body.email });
      return res.json({
        message: 'If an account matches, a reset link has been dispatched via the deterministic email adapter.',
        email: outcome.email,
        ...(config.isProduction ? {} : { dev_reset_url: outcome.resetUrl }),
      });
    }
    case 'reset_confirm': {
      requireFields(req.body, ['token', 'password']);
      if (req.body.password.length < 8) throw new AppError(400, 'VALIDATION_ERROR', 'Password must be at least 8 characters.');
      confirmPasswordReset({ token: req.body.token, password: req.body.password });
      return res.json({ ok: true, message: 'Password updated. Please sign in with your new password.' });
    }
    default:
      throw new AppError(400, 'VALIDATION_ERROR', 'Unknown account access action.');
  }
});

// PATCH /api/hotel/account_access/:id
router.patch('/:id', requireAuth, (req, res) => {
  const id = Number(req.params.id);
  const { action } = req.body;
  requireFields(req.body, ['action']);

  if (action === 'revoke_session') {
    const ok = revokeSessionById(req.user.id, id);
    if (!ok) throw new AppError(404, 'SESSION_NOT_FOUND', 'Session not found for this account.');
    return res.json({ ok: true, message: 'Session revoked.' });
  }

  if (action === 'update_profile') {
    if (id !== req.user.id) throw new AppError(403, 'FORBIDDEN', 'You can only update your own profile.');
    const db = getDb();
    const updates = [];
    const values = [];
    if (req.body.name !== undefined) { updates.push('name = ?'); values.push(req.body.name.trim()); }
    if (req.body.phone !== undefined) { updates.push('phone = ?'); values.push(req.body.phone); }
    if (updates.length === 0) throw new AppError(400, 'VALIDATION_ERROR', 'Nothing to update.');
    updates.push('updated_at = ?');
    values.push(new Date().toISOString().slice(0, 19).replace('T', ' '));
    values.push(id);
    db.prepare(`UPDATE users SET ${updates.join(', ')} WHERE id = ?`).run(...values);
    const user = db.prepare('SELECT id, email, name, role, phone, created_at FROM users WHERE id = ?').get(id);
    return res.json({ user, message: 'Profile updated.' });
  }

  throw new AppError(400, 'VALIDATION_ERROR', 'Unknown account access patch action.');
});

export default router;
