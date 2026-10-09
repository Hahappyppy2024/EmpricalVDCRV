import { Router } from 'express';
import { getDb } from '../db/database.js';
import { requireAuth, requireRole } from '../middleware/auth.js';
import { validationError, notFoundError, authError, forbidError } from '../services/errors.js';
import { broadcast } from '../services/realtime.js';

const router = Router();

router.get('/comments', (req, res) => {
  const db = getDb();
  const articleId = Number(req.query.articleId);
  const state = req.query.state;
  // Public: only approved
  const moderatorView = req.user && (req.user.role_name === 'moderator' || req.user.role_name === 'admin' || req.user.role_name === 'editor');
  if (!moderatorView) {
    const params = [];
    let where = "c.state = 'approved'";
    if (articleId) { where += ' AND c.article_id = ?'; params.push(articleId); }
    const rows = db.prepare(`
      SELECT c.id, c.article_id, c.author_name, c.body, c.created_at, a.slug as article_slug, a.title as article_title
      FROM comments c
      JOIN content_authoring a ON a.id = c.article_id
      WHERE ${where}
      ORDER BY c.created_at DESC
    `).all(...params);
    return res.json({ comments: rows });
  }
  const params = [];
  let where = '1=1';
  if (articleId) { where += ' AND c.article_id = ?'; params.push(articleId); }
  if (state) { where += ' AND c.state = ?'; params.push(state); }
  const rows = db.prepare(`
    SELECT c.*, a.slug as article_slug, a.title as article_title, u.display_name as moderator_name
    FROM comments c
    JOIN content_authoring a ON a.id = c.article_id
    LEFT JOIN users u ON u.id = c.moderator_id
    WHERE ${where}
    ORDER BY c.created_at DESC
  `).all(...params);
  res.json({ comments: rows });
});

router.post('/comments', (req, res) => {
  const { articleId, body, authorName, authorEmail } = req.body || {};
  if (!articleId || typeof articleId !== 'number') throw validationError('articleId required', ['articleId']);
  if (!body || typeof body !== 'string' || body.length < 2) throw validationError('Comment body required', ['body']);
  if (!authorName || typeof authorName !== 'string') throw validationError('Author name required', ['authorName']);
  const db = getDb();
  const article = db.prepare("SELECT * FROM content_authoring WHERE id = ? AND status = 'published'").get(articleId);
  if (!article) throw notFoundError('Article not found or not published');
  // Determine state: require_moderation setting
  const setting = db.prepare("SELECT value FROM plugin_settings_panel WHERE key = 'comments.require_moderation'").get();
  const requireModeration = !setting || setting.value === 'true';
  const initialState = requireModeration ? 'pending' : 'approved';
  const authorId = req.user?.id || null;
  const info = db.prepare(`
    INSERT INTO comments (article_id, author_name, author_email, author_id, body, state)
    VALUES (?, ?, ?, ?, ?, ?)
  `).run(articleId, authorName, authorEmail || null, authorId, body, initialState);
  const created = db.prepare('SELECT * FROM comments WHERE id = ?').get(info.lastInsertRowid);
  try { broadcast('comment.created', { id: created.id, articleId: created.article_id, state: created.state }); } catch { /* noop */ }
  res.status(201).json({ comment: created, message: initialState === 'pending' ? 'pending_moderation' : 'approved' });
});

router.patch('/comments/:id', requireAuth, requireRole('moderator', 'admin', 'editor'), (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const comment = db.prepare('SELECT * FROM comments WHERE id = ?').get(id);
  if (!comment) throw notFoundError('Comment not found');
  const updates = [];
  const params = [];
  if (req.body?.state && ['approved', 'pending', 'rejected', 'removed'].includes(req.body.state)) {
    updates.push('state = ?'); params.push(req.body.state);
    updates.push('moderator_id = ?'); params.push(req.user.id);
  }
  if (typeof req.body?.body === 'string') { updates.push('body = ?'); params.push(req.body.body); }
  if (!updates.length) return res.json({ comment });
  params.push(id);
  db.prepare(`UPDATE comments SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  const updated = db.prepare('SELECT * FROM comments WHERE id = ?').get(id);
  try { broadcast('comment.updated', { id: updated.id, state: updated.state }); } catch { /* noop */ }
  res.json({ comment: updated, message: 'updated' });
});

export default router;
