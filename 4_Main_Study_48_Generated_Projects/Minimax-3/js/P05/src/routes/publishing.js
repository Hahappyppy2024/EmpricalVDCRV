import { Router } from 'express';
import { getDb } from '../db/database.js';
import { requireAuth, requireRole } from '../middleware/auth.js';
import { validationError, notFoundError, forbidError } from '../services/errors.js';

const router = Router();

const VALID_TRANSITIONS = {
  draft: ['review'],
  review: ['draft', 'scheduled', 'published'],
  scheduled: ['draft', 'published'],
  published: ['draft', 'archived'],
  archived: ['draft']
};

function canTransition(from, to) {
  if (!VALID_TRANSITIONS[from]) return false;
  return VALID_TRANSITIONS[from].includes(to);
}

router.get('/publishing_workflow', requireAuth, (req, res) => {
  const db = getDb();
  const items = db.prepare(`
    SELECT w.*, a.title as article_title, a.slug as article_slug, u.display_name as reviewer_name
    FROM publishing_workflow w
    JOIN content_authoring a ON a.id = w.article_id
    LEFT JOIN users u ON u.id = w.reviewer_id
    ORDER BY w.id DESC
  `).all();
  res.json({ items });
});

router.post('/publishing_workflow', requireAuth, requireRole('admin', 'editor'), (req, res) => {
  const { articleId, action, scheduledAt, notes } = req.body || {};
  if (!articleId) throw validationError('articleId required', ['articleId']);
  const targetState = action;
  if (!['draft', 'review', 'scheduled', 'published', 'archived'].includes(targetState)) {
    throw validationError('Invalid target state', ['action']);
  }
  const db = getDb();
  const article = db.prepare('SELECT * FROM content_authoring WHERE id = ?').get(Number(articleId));
  if (!article) throw notFoundError('Article not found');
  const current = db.prepare('SELECT * FROM publishing_workflow WHERE article_id = ? ORDER BY id DESC LIMIT 1').get(article.id);
  const fromState = current?.state || 'draft';
  if (!canTransition(fromState, targetState)) {
    return res.status(409).json({ error: 'conflict', message: `Cannot transition from ${fromState} to ${targetState}` });
  }
  const publishedAt = targetState === 'published' ? new Date().toISOString().replace('T', ' ').substring(0, 19) : null;
  const reviewerId = targetState === 'review' ? req.user.id : current?.reviewer_id || null;
  const info = db.prepare(`
    INSERT INTO publishing_workflow (article_id, state, scheduled_at, published_at, reviewer_id, notes)
    VALUES (?, ?, ?, ?, ?, ?)
  `).run(article.id, targetState, targetState === 'scheduled' ? (scheduledAt || null) : null, publishedAt, reviewerId, notes || null);

  // Sync article.status to reflect workflow state when published
  if (targetState === 'published') {
    db.prepare("UPDATE content_authoring SET status = 'published', updated_at = datetime('now') WHERE id = ?").run(article.id);
    // Mirror to public_site
    const existing = db.prepare('SELECT id FROM public_site WHERE article_id = ?').get(article.id);
    if (!existing) {
      db.prepare(
        'INSERT INTO public_site (route_path, title, excerpt, body, article_id, template_id) VALUES (?, ?, ?, ?, ?, ?)'
      ).run(`/articles/${article.slug}`, article.title, article.summary || '', article.body || '', article.id, article.template_id);
    } else {
      db.prepare("UPDATE public_site SET title = ?, excerpt = ?, body = ?, published_at = datetime('now') WHERE article_id = ?")
        .run(article.title, article.summary || '', article.body || '', article.id);
    }
  } else if (targetState === 'archived') {
    db.prepare("UPDATE content_authoring SET status = 'archived', updated_at = datetime('now') WHERE id = ?").run(article.id);
  } else if (targetState === 'draft' || targetState === 'review') {
    db.prepare("UPDATE content_authoring SET status = ?, updated_at = datetime('now') WHERE id = ?").run(targetState, article.id);
  }

  const created = db.prepare('SELECT * FROM publishing_workflow WHERE id = ?').get(info.lastInsertRowid);
  res.status(201).json({ item: created, message: 'transitioned' });
});

router.patch('/publishing_workflow/:id', requireAuth, requireRole('admin', 'editor'), (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const wf = db.prepare('SELECT * FROM publishing_workflow WHERE id = ?').get(id);
  if (!wf) throw notFoundError('Workflow entry not found');
  const updates = [];
  const params = [];
  if (typeof req.body?.notes === 'string') { updates.push('notes = ?'); params.push(req.body.notes); }
  if (req.body?.scheduledAt) { updates.push('scheduled_at = ?'); params.push(req.body.scheduledAt); }
  if (!updates.length) return res.json({ item: wf });
  params.push(id);
  db.prepare(`UPDATE publishing_workflow SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  const updated = db.prepare('SELECT * FROM publishing_workflow WHERE id = ?').get(id);
  res.json({ item: updated, message: 'updated' });
});

export default router;
