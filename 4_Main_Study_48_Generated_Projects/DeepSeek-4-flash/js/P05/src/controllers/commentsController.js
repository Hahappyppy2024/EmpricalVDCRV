import { getDb } from '../db/index.js';
import { apiError, ok } from '../lib/http.js';
import { hasErrors, validate } from '../lib/validate.js';
import { broadcast } from '../services/realTime.js';
import { audit } from '../services/audit.js';

const MODERATORS = ['moderator', 'editor', 'admin'];

function commentsSelect() {
  return `
    SELECT c.*, a.title AS article_title, a.slug AS article_slug, u.username AS moderator_username
    FROM comments c
    LEFT JOIN articles a ON a.id = c.article_id
    LEFT JOIN users u ON u.id = c.moderated_by`;
}

export async function listComments(req, res, next) {
  try {
    const db = getDb();
    const { articleId, status, page = 1, pageSize = 50 } = req.query;
    const params = { limit: Math.max(1, Number(pageSize) || 50), offset: (Math.max(1, Number(page) || 1) - 1) * (Math.max(1, Number(pageSize) || 50)) };
    const clauses = [];

    if (articleId) {
      clauses.push('c.article_id = @articleId');
      params.articleId = Number(articleId);
    }

    const isStaff = MODERATORS.includes(req.user ? req.user.role_name : null);
    if (!isStaff) {
      clauses.push("c.status = 'approved'");
    } else if (status) {
      clauses.push('c.status = @status');
      params.status = status;
    }

    const where = clauses.length ? `WHERE ${clauses.join(' AND ')}` : '';
    const count = db.prepare(`SELECT COUNT(*) AS c FROM comments c ${where}`).get(params).c;
    const rows = db.prepare(`${commentsSelect()} ${where} ORDER BY c.id DESC LIMIT @limit OFFSET @offset`).all(params);
    return ok(res, { data: rows, meta: { page: Number(page) || 1, pageSize: Number(pageSize) || 50, total: count } }, 'Comments.');
  } catch (err) {
    return next(err);
  }
}

export async function createComment(req, res, next) {
  try {
    const db = getDb();
    const body = req.body || {};
    const errors = validate(body, {
      articleId: { type: 'number', required: true, label: 'Article' },
      authorName: { type: 'string', required: true, minLength: 2, maxLength: 80, label: 'Name' },
      authorEmail: { type: 'string', email: true, maxLength: 120, label: 'Email' },
      body: { type: 'string', required: true, minLength: 1, maxLength: 4000, label: 'Comment' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    const article = db.prepare('SELECT id, title FROM articles WHERE id = ? AND status = \'published\'').get(Number(body.articleId));
    if (!article) return next(apiError(404, 'NOT_FOUND', 'Published article not found.'));
    const allowComments = db.prepare("SELECT value FROM plugin_settings_panel WHERE key = 'allow_comments'").get();
    if (allowComments && allowComments.value === 'false') {
      return next(apiError(403, 'COMMENTS_DISABLED', 'Comments are currently disabled on this site.'));
    }
    const moderate = db.prepare("SELECT value FROM plugin_settings_panel WHERE key = 'moderate_comments'").get();
    const initialStatus = moderate && moderate.value === 'true' ? 'pending' : 'approved';

    const id = db.prepare(`INSERT INTO comments (article_id, author_name, author_email, body, status)
      VALUES (?, ?, ?, ?, ?)`)
      .run(article.id, body.authorName, body.authorEmail || null, body.body, initialStatus).lastInsertRowid;

    const record = db.prepare(`${commentsSelect()} WHERE c.id = ?`).get(id);
    broadcast('comment:new', { commentId: id, articleId: article.id, articleTitle: article.title, status: initialStatus });
    return ok(res, { data: record }, initialStatus === 'pending' ? 'Comment submitted and is awaiting moderation.' : 'Comment posted.', 201);
  } catch (err) {
    return next(err);
  }
}

export async function patchComment(req, res, next) {
  try {
    if (!MODERATORS.includes(req.user.role_name)) {
      return next(apiError(403, 'FORBIDDEN', 'Only a moderator can moderate comments.'));
    }
    const db = getDb();
    const id = Number(req.params.id);
    const comment = db.prepare('SELECT * FROM comments WHERE id = ?').get(id);
    if (!comment) return next(apiError(404, 'NOT_FOUND', 'Comment not found.'));

    const body = req.body || {};
    if (body.status !== undefined) {
      if (!['pending', 'approved', 'removed'].includes(body.status)) {
        return next(apiError(400, 'VALIDATION_ERROR', 'Status must be one of: pending, approved, removed.'));
      }
      const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
      db.prepare(`UPDATE comments SET status = ?, moderated_by = ?, moderated_at = ? WHERE id = ?`)
        .run(body.status, req.user.id, now, id);
    }
    if (body.body !== undefined) {
      if (typeof body.body !== 'string' || body.body.length === 0 || body.body.length > 4000) {
        return next(apiError(400, 'VALIDATION_ERROR', 'Comment must be between 1 and 4000 characters.'));
      }
      db.prepare('UPDATE comments SET body = ? WHERE id = ?').run(body.body, id);
    }

    audit(db, { actorId: req.user.id, action: 'moderate_comment', entityType: 'comments', entityId: id, details: `Set status to ${body.status || 'unchanged'}` });
    const updated = db.prepare(`${commentsSelect()} WHERE c.id = ?`).get(id);
    broadcast('comment:moderated', { commentId: id, articleId: updated.article_id, status: updated.status, moderatedBy: req.user.username });
    return ok(res, { data: updated }, 'Comment moderated.');
  } catch (err) {
    return next(err);
  }
}

export async function deleteComment(req, res, next) {
  try {
    if (!MODERATORS.includes(req.user.role_name)) {
      return next(apiError(403, 'FORBIDDEN', 'Only a moderator can delete comments.'));
    }
    const db = getDb();
    const id = Number(req.params.id);
    const comment = db.prepare('SELECT * FROM comments WHERE id = ?').get(id);
    if (!comment) return next(apiError(404, 'NOT_FOUND', 'Comment not found.'));
    db.prepare('DELETE FROM comments WHERE id = ?').run(id);
    audit(db, { actorId: req.user.id, action: 'delete_comment', entityType: 'comments', entityId: id, details: 'Deleted comment' });
    broadcast('comment:moderated', { commentId: id, status: 'deleted' });
    return ok(res, { data: { id, deleted: true } }, 'Comment deleted.');
  } catch (err) {
    return next(err);
  }
}
