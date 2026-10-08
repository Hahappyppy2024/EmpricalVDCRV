import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import * as authService from '../services/authService.js';

export function accountAccessRoutes() {
  const router = Router();

  router.use(loadSession);

  router.get('/', requireAuth, (req, res) => {
    const db = getDb();
    res.json({
      ok: true,
      data: {
        account: authService.publicUser(req.user),
        sessions: authService.listSessions(db, req.user.id),
        dashboard: authService.dashboardFor(db, req.user),
      },
    });
  });

  router.post('/', loadSession, (req, res) => {
    const db = getDb();
    const b = req.body || {};
    if (b.action === 'register') {
      const user = authService.register(db, {
        username: b.username,
        password: b.password,
        fullName: b.full_name,
        email: b.email,
        departmentId: b.department_id,
        costCenterId: b.cost_center_id,
        managerId: b.manager_id,
      });
      return res.status(201).json({ ok: true, data: { user, message: 'Account created' } });
    }
    const { username, password } = b;
    const result = authService.login(db, { username, password, ip: req.ip, userAgent: req.headers['user-agent'] });
    authService.setSessionCookie(res, result.session.id);
    res.json({ ok: true, data: { user: result.user, session: result.session, message: 'Account access granted' } });
  });

  router.patch('/:id', requireAuth, (req, res) => {
    const db = getDb();
    if (req.params.id === 'self') {
      const b = req.body || {};
      const user = authService.updateProfile(db, req.user, {
        fullName: b.full_name,
        email: b.email,
        oldPassword: b.old_password,
        newPassword: b.new_password,
      });
      return res.json({ ok: true, data: { user, message: 'Account updated' } });
    }
    const sessionId = req.params.id;
    const body = req.body || {};
    if (body.action === 'revoke') {
      authService.revokeSession(db, req.user.id, sessionId);
      return res.json({ ok: true, data: { message: 'Session revoked' } });
    }
    res.json({ ok: true, data: { message: 'Nothing to update' } });
  });

  return router;
}
