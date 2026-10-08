import { getDb } from '../db/index.js';
import { apiError, ok } from '../lib/http.js';
import { sanitizeHtml } from '../lib/sanitize.js';
import { hasErrors, validate } from '../lib/validate.js';
import { broadcast } from '../services/realTime.js';

function canAccessDocument(user, doc, article) {
  if (['editor', 'admin'].includes(user.role_name)) return true;
  if (article && article.author_id === user.id) return true;
  return doc.updated_by === user.id;
}

export async function listRichTextEditor(req, res, next) {
  try {
    const db = getDb();
    const { articleId, page = 1, pageSize = 50 } = req.query;
    const params = { limit: Math.max(1, Number(pageSize) || 50), offset: (Math.max(1, Number(page) || 1) - 1) * (Math.max(1, Number(pageSize) || 50)) };
    const clauses = [];

    if (articleId) {
      clauses.push('r.article_id = @articleId');
      params.articleId = Number(articleId);
    }
    if (req.user.role_name === 'author') {
      clauses.push('(r.updated_by = @userId OR a.author_id = @userId)');
      params.userId = req.user.id;
    }
    const where = clauses.length ? `WHERE ${clauses.join(' AND ')}` : '';
    const count = db.prepare(`SELECT COUNT(*) AS c FROM rich_text_editor r LEFT JOIN articles a ON a.id = r.article_id ${where}`).get(params).c;
    const rows = db.prepare(`
      SELECT r.*, u.username AS updated_by_username, a.title AS article_title
      FROM rich_text_editor r
      LEFT JOIN users u ON u.id = r.updated_by
      LEFT JOIN articles a ON a.id = r.article_id
      ${where}
      ORDER BY r.id DESC LIMIT @limit OFFSET @offset
    `).all(params);
    return ok(res, { data: rows, meta: { page: Number(page) || 1, pageSize: Number(pageSize) || 50, total: count } }, 'Rich text documents.');
  } catch (err) {
    return next(err);
  }
}

export async function createRichTextEditor(req, res, next) {
  try {
    const db = getDb();
    const body = req.body || {};
    const errors = validate(body, {
      articleId: { type: 'number', required: true, label: 'Article' },
      content: { type: 'string', required: true, minLength: 1, maxLength: 100000, label: 'Rich text content' },
      contentHtml: { type: 'string', maxLength: 200000, label: 'Rich text HTML' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    const article = db.prepare('SELECT * FROM articles WHERE id = ?').get(Number(body.articleId));
    if (!article) return next(apiError(404, 'NOT_FOUND', 'Article not found.'));
    const canEdit = ['editor', 'admin'].includes(req.user.role_name) || article.author_id === req.user.id;
    if (!canEdit) return next(apiError(403, 'FORBIDDEN', 'You do not have permission to edit this article.'));

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const version = db.prepare('SELECT COALESCE(MAX(version), 0) + 1 AS v FROM rich_text_editor WHERE article_id = ?').get(article.id).v;
    const docId = db.prepare(`INSERT INTO rich_text_editor (article_id, content, content_html, version, updated_by, created_at, updated_at)
      VALUES (?, ?, ?, ?, ?, ?, ?)`)
      .run(article.id, body.content, sanitizeHtml(body.contentHtml || body.content), version, req.user.id, now, now).lastInsertRowid;

    db.prepare('UPDATE articles SET body = ?, body_html = ?, updated_at = ? WHERE id = ?')
      .run(body.content, sanitizeHtml(body.contentHtml || body.content), now, article.id);

    const doc = db.prepare(`
      SELECT r.*, u.username AS updated_by_username, a.title AS article_title
      FROM rich_text_editor r
      LEFT JOIN users u ON u.id = r.updated_by
      LEFT JOIN articles a ON a.id = r.article_id
      WHERE r.id = ?
    `).get(docId);
    broadcast('editor:updated', { articleId: article.id, articleTitle: article.title, version });
    return ok(res, { data: doc }, 'Rich text document saved.', 201);
  } catch (err) {
    return next(err);
  }
}

export async function patchRichTextEditor(req, res, next) {
  try {
    const db = getDb();
    const id = Number(req.params.id);
    const doc = db.prepare('SELECT * FROM rich_text_editor WHERE id = ?').get(id);
    if (!doc) return next(apiError(404, 'NOT_FOUND', 'Rich text document not found.'));

    const article = doc.article_id ? db.prepare('SELECT * FROM articles WHERE id = ?').get(doc.article_id) : null;
    if (!canAccessDocument(req.user, doc, article)) {
      return next(apiError(403, 'FORBIDDEN', 'You do not have permission to update this document.'));
    }

    const body = req.body || {};
    const errors = validate(body, {
      content: { type: 'string', maxLength: 100000, label: 'Rich text content' },
      contentHtml: { type: 'string', maxLength: 200000, label: 'Rich text HTML' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    db.prepare(`UPDATE rich_text_editor SET content = COALESCE(@content, content), content_html = COALESCE(@contentHtml, content_html), updated_by = @userId, updated_at = @now WHERE id = @id`)
      .run({ content: body.content, contentHtml: body.contentHtml ? sanitizeHtml(body.contentHtml) : null, userId: req.user.id, now, id });

    if (article) {
      const content = body.content !== undefined ? body.content : doc.content;
      const contentHtml = body.contentHtml !== undefined ? sanitizeHtml(body.contentHtml) : doc.content_html;
      db.prepare('UPDATE articles SET body = ?, body_html = ?, updated_at = ? WHERE id = ?').run(content, contentHtml, now, article.id);
      broadcast('editor:updated', { articleId: article.id, articleTitle: article.title });
    }

    const updated = db.prepare(`
      SELECT r.*, u.username AS updated_by_username, a.title AS article_title
      FROM rich_text_editor r
      LEFT JOIN users u ON u.id = r.updated_by
      LEFT JOIN articles a ON a.id = r.article_id
      WHERE r.id = ?
    `).get(id);
    return ok(res, { data: updated }, 'Rich text document updated.');
  } catch (err) {
    return next(err);
  }
}
