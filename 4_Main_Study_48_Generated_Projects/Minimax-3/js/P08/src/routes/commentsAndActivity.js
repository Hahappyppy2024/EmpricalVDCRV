import express from 'express';
import { db } from '../db/index.js';
import { ok, fail } from '../middleware/http.js';
import { notifyReportSubscribers } from '../services/websocketService.js';

const router = express.Router();

function ensureCanAccessReport(session, report) {
  const user = session.user;
  if (user.role === 'admin' || user.role === 'finance') return;
  if (user.role === 'manager') {
    const owner = db.prepare('SELECT manager_id FROM users WHERE id = ?').get(report.employee_id);
    if (owner?.manager_id !== user.id && report.employee_id !== user.id) {
      const e = new Error('Forbidden'); e.status = 403; throw e;
    }
    return;
  }
  if (report.employee_id !== user.id) {
    const e = new Error('Forbidden'); e.status = 403; throw e;
  }
}

router.get('/comments_and_activity', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const reportId = Number(req.query.reportId);
  if (!reportId) return fail(res, 'reportId is required', 400, 'validation_error');
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(reportId);
  if (!report) return fail(res, 'Not found', 404, 'not_found');
  try { ensureCanAccessReport(session, report); }
  catch (e) { return fail(res, e.message, e.status, e.code); }
  const comments = db.prepare(`
    SELECT c.*, u.username, u.full_name, u.role FROM comments c
    JOIN users u ON u.id = c.author_id
    WHERE c.report_id = ? ORDER BY c.created_at ASC
  `).all(reportId);
  const activity = db.prepare(`
    SELECT a.*, u.username FROM activity_logs a
    LEFT JOIN users u ON u.id = a.actor_id
    WHERE a.report_id = ? ORDER BY a.created_at ASC
  `).all(reportId);
  return ok(res, { comments, activity });
});

router.post('/comments_and_activity', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const { reportId, body } = req.body || {};
  if (!reportId || !body || !String(body).trim()) return fail(res, 'reportId and body required', 400, 'validation_error');
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(Number(reportId));
  if (!report) return fail(res, 'Not found', 404, 'not_found');
  try { ensureCanAccessReport(session, report); }
  catch (e) { return fail(res, e.message, e.status, e.code); }
  const info = db.prepare(`
    INSERT INTO comments (report_id, author_id, body) VALUES (?, ?, ?)
  `).run(report.id, session.user.id, String(body).trim());
  const stored = db.prepare(`
    SELECT c.*, u.username, u.full_name, u.role FROM comments c
    JOIN users u ON u.id = c.author_id WHERE c.id = ?
  `).get(info.lastInsertRowid);
  db.prepare(`INSERT INTO activity_logs (actor_id, report_id, event_type, details) VALUES (?, ?, 'comment_added', ?)`)
    .run(session.user.id, report.id, JSON.stringify({ commentId: info.lastInsertRowid }));
  notifyReportSubscribers(report.id, { type: 'comment', data: stored });
  return ok(res, stored, 201);
});

router.patch('/comments_and_activity/:id', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  const id = Number(req.params.id);
  const comment = db.prepare('SELECT * FROM comments WHERE id = ?').get(id);
  if (!comment) return fail(res, 'Not found', 404, 'not_found');
  if (comment.author_id !== session.user.id && session.user.role !== 'admin') {
    return fail(res, 'Cannot edit comment', 403, 'forbidden');
  }
  const { body } = req.body || {};
  if (!body || !String(body).trim()) return fail(res, 'body is required', 400, 'validation_error');
  db.prepare('UPDATE comments SET body = ? WHERE id = ?').run(String(body).trim(), id);
  return ok(res, { updated: true });
});

export default router;
