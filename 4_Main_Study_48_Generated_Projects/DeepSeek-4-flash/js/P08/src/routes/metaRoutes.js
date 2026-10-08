import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import { listAudit } from '../services/auditService.js';

export function metaRoutes() {
  const router = Router();
  router.use(loadSession, requireAuth);

  router.get('/lookups', (req, res) => {
    const db = getDb();
    res.json({
      ok: true,
      data: {
        departments: db.prepare('SELECT * FROM departments ORDER BY id').all(),
        cost_centers: db.prepare('SELECT * FROM cost_centers ORDER BY id').all(),
        categories: db.prepare('SELECT * FROM categories ORDER BY id').all(),
        managers: db
          .prepare(
            `SELECT id, username, full_name, role FROM users WHERE role IN ('manager','admin') AND active = 1 ORDER BY id`
          )
          .all(),
      },
    });
  });

  router.get('/audit', (req, res) => {
    const db = getDb();
    if (req.user.role !== 'admin') {
      return res.status(403).json({ ok: false, error: { code: 'UNAUTHORIZED', message: 'Only admin can view the audit trail' } });
    }
    res.json({ ok: true, data: { events: listAudit(db, { limit: 200 }) } });
  });

  return router;
}
