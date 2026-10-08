import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import * as adminService from '../services/adminService.js';

export function adminRoutes() {
  const router = Router();
  router.use(loadSession, requireAuth);

  router.get('/', (req, res) => {
    const db = getDb();
    if (req.user.role !== 'admin') {
      return res.status(403).json({ ok: false, error: { code: 'UNAUTHORIZED', message: 'Only admin can view configuration' } });
    }
    res.json({ ok: true, data: adminService.adminOverview(db) });
  });

  router.post('/', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const result = adminService.createConfiguration(db, req.user, { type: b.type, ...b.payload });
    res.status(201).json({ ok: true, data: result });
  });

  router.patch('/:id', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const result = adminService.updateConfiguration(db, req.user, Number(req.params.id), { type: b.type, ...b.payload });
    res.json({ ok: true, data: result });
  });

  return router;
}
