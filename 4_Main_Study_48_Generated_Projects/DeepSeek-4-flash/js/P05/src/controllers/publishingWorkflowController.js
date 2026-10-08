import { getDb } from '../db/index.js';
import { apiError, ok } from '../lib/http.js';
import { hasErrors, validate } from '../lib/validate.js';
import { applyTransition, flushScheduled } from '../services/publishing.js';

export async function listPublishingWorkflow(req, res, next) {
  try {
    const db = getDb();
    const { articleId, toStatus, page = 1, pageSize = 50 } = req.query;
    const params = { limit: Math.max(1, Number(pageSize) || 50), offset: (Math.max(1, Number(page) || 1) - 1) * (Math.max(1, Number(pageSize) || 50)) };
    const clauses = [];
    if (articleId) {
      clauses.push('w.article_id = @articleId');
      params.articleId = Number(articleId);
    }
    if (toStatus) {
      clauses.push('w.to_status = @toStatus');
      params.toStatus = toStatus;
    }
    if (req.user.role_name === 'author') {
      clauses.push('a.author_id = @userId');
      params.userId = req.user.id;
    }
    const where = clauses.length ? `WHERE ${clauses.join(' AND ')}` : '';
    const count = db.prepare(`SELECT COUNT(*) AS c FROM publishing_workflow w JOIN articles a ON a.id = w.article_id ${where}`).get(params).c;
    const rows = db.prepare(`
      SELECT w.*, a.title AS article_title, a.slug AS article_slug, a.status AS current_status,
             u.username AS actor_username
      FROM publishing_workflow w
      JOIN articles a ON a.id = w.article_id
      LEFT JOIN users u ON u.id = w.actor_id
      ${where}
      ORDER BY w.id DESC LIMIT @limit OFFSET @offset
    `).all(params);
    return ok(res, { data: rows, meta: { page: Number(page) || 1, pageSize: Number(pageSize) || 50, total: count } }, 'Publishing workflow history.');
  } catch (err) {
    return next(err);
  }
}

export async function publishingQueue(req, res, next) {
  try {
    const db = getDb();
    flushScheduled(db);
    const rows = db.prepare(`
      SELECT a.id, a.title, a.slug, a.status, a.publish_at, a.published_at,
             u.username AS author_username,
             c.name AS category_name,
             (SELECT COUNT(*) FROM comments cm WHERE cm.article_id = a.id AND cm.status = 'pending') AS pending_comments
      FROM articles a
      LEFT JOIN users u ON u.id = a.author_id
      LEFT JOIN categories c ON c.id = a.category_id
      WHERE a.status IN ('review', 'scheduled')
      ORDER BY CASE a.status WHEN 'review' THEN 0 ELSE 1 END, a.id DESC
    `).all();
    return ok(res, { data: rows }, 'Publishing queue.');
  } catch (err) {
    return next(err);
  }
}

export async function createPublishingWorkflow(req, res, next) {
  try {
    const db = getDb();
    const body = req.body || {};
    const errors = validate(body, {
      articleId: { type: 'number', required: true, label: 'Article' },
      toStatus: { type: 'string', required: true, oneOf: ['draft', 'review', 'scheduled', 'published'], label: 'Target status' },
      note: { type: 'string', maxLength: 500, label: 'Note' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    const article = db.prepare('SELECT * FROM articles WHERE id = ?').get(Number(body.articleId));
    if (!article) return next(apiError(404, 'NOT_FOUND', 'Article not found.'));

    if (req.user.role_name === 'author' && article.author_id !== req.user.id) {
      return next(apiError(403, 'FORBIDDEN', 'You can only advance your own articles through the workflow.'));
    }

    const record = applyTransition({
      article,
      toStatus: body.toStatus,
      actor: { id: req.user.id, username: req.user.username, role_name: req.user.role_name },
      note: body.note
    });
    return ok(res, { data: record }, `Article moved to "${body.toStatus}".`, 201);
  } catch (err) {
    return next(err);
  }
}

export async function patchPublishingWorkflow(req, res, next) {
  try {
    const db = getDb();
    const id = Number(req.params.id);
    const record = db.prepare('SELECT * FROM publishing_workflow WHERE id = ?').get(id);
    if (!record) return next(apiError(404, 'NOT_FOUND', 'Workflow record not found.'));

    const canUpdate = req.user.role_name === 'admin' || record.actor_id === req.user.id;
    if (!canUpdate) return next(apiError(403, 'FORBIDDEN', 'You do not have permission to update this record.'));

    const body = req.body || {};
    if (body.note !== undefined) {
      if (typeof body.note !== 'string' || body.note.length > 500) {
        return next(apiError(400, 'VALIDATION_ERROR', 'Note must be a string of at most 500 characters.'));
      }
      db.prepare('UPDATE publishing_workflow SET note = ? WHERE id = ?').run(body.note, id);
    }
    const updated = db.prepare(`
      SELECT w.*, a.title AS article_title, u.username AS actor_username
      FROM publishing_workflow w
      JOIN articles a ON a.id = w.article_id
      LEFT JOIN users u ON u.id = w.actor_id
      WHERE w.id = ?
    `).get(id);
    return ok(res, { data: updated }, 'Workflow record updated.');
  } catch (err) {
    return next(err);
  }
}
