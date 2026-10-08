import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import * as authService from '../services/authService.js';

export function authRoutes() {
  const router = Router();

  router.get('/me', loadSession, requireAuth, (req, res) => {
    res.json({ ok: true, data: authService.publicUser(req.user) });
  });

  router.post('/login', loadSession, (req, res) => {
    const db = getDb();
    const { username, password } = req.body || {};
    const result = authService.login(db, {
      username,
      password,
      ip: req.ip,
      userAgent: req.headers['user-agent'],
    });
    authService.setSessionCookie(res, result.session.id);
    res.json({ ok: true, data: { user: result.user, dashboard: authService.dashboardFor(db, result.user), session: { id: result.session.id, expires_at: result.session.expiresAt } } });
  });

  router.post('/register', loadSession, (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const user = authService.register(db, {
      username: b.username,
      password: b.password,
      fullName: b.full_name,
      email: b.email,
      departmentId: b.department_id,
      costCenterId: b.cost_center_id,
      managerId: b.manager_id,
    });
    res.status(201).json({ ok: true, data: user });
  });

  router.post('/logout', loadSession, (req, res) => {
    const db = getDb();
    authService.logout(db, req, res);
    res.json({ ok: true, data: { message: 'Signed out' } });
  });

  router.get('/dashboard', loadSession, requireAuth, (req, res) => {
    const db = getDb();
    res.json({ ok: true, data: authService.dashboardFor(db, req.user) });
  });

  return router;
}
