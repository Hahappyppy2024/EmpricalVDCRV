import { getDb } from '../db/index.js';
import { apiError, ok } from '../lib/http.js';
import { slugify } from '../lib/crypto.js';
import { sanitizeHtml } from '../lib/sanitize.js';
import { hasErrors, validate } from '../lib/validate.js';
import { audit } from '../services/audit.js';
import { broadcast } from '../services/realTime.js';

const ARTICLE_STATUSES = ['draft', 'review', 'scheduled', 'published'];

function uniqueSlug(db, base, ignoreId = null) {
  let slug = base || 'untitled';
  let candidate = slug;
  let i = 2;
  const stmt = db.prepare('SELECT id FROM articles WHERE slug = ?' + (ignoreId ? ' AND id != ?' : ''));
  while (true) {
    const row = ignoreId ? stmt.get(candidate, ignoreId) : stmt.get(candidate);
    if (!row) return candidate;
    candidate = `${slug}-${i}`;
    i += 1;
  }
}

function articleSelect() {
  return `
    SELECT a.*, u.username AS author_username, u.display_name AS author_display_name,
           e.username AS editor_username,
           c.name AS category_name, c.slug AS category_slug,
           t.slug AS template_slug, t.name AS template_name,
           '/api/cms/media_library/' || m.id || '/file' AS featured_media_url, m.alt_text AS featured_media_alt
    FROM articles a
    LEFT JOIN users u ON u.id = a.author_id
    LEFT JOIN users e ON e.id = a.editor_id
    LEFT JOIN categories c ON c.id = a.category_id
    LEFT JOIN page_templates t ON t.id = a.template_id
    LEFT JOIN media_library m ON m.id = a.featured_media_id`;
}

function canEditArticle(user, article) {
  if (['editor', 'admin'].includes(user.role_name)) return true;
  if (user.role_name === 'author' && article.author_id === user.id && ['draft', 'review'].includes(article.status)) return true;
  return false;
}

function canViewArticle(user, article) {
  if (['editor', 'admin'].includes(user.role_name)) return true;
  if (article.status === 'published') return true;
  return article.author_id === user.id;
}

function parseTags(tags) {
  if (Array.isArray(tags)) return JSON.stringify(tags.map(String).slice(0, 20));
  if (typeof tags === 'string') {
    if (tags.startsWith('[')) {
      try {
        return JSON.stringify(JSON.parse(tags).map(String).slice(0, 20));
      } catch {
        // fall through
      }
    }
    return JSON.stringify(tags.split(',').map((t) => t.trim()).filter(Boolean).slice(0, 20));
  }
  return '[]';
}

export async function listContentAuthoring(req, res, next) {
  try {
    const db = getDb();
    const { status, category, q, page = 1, pageSize = 50 } = req.query;
    const clauses = [];
    const params = { limit: Math.max(1, Number(pageSize) || 50), offset: (Math.max(1, Number(page) || 1) - 1) * (Math.max(1, Number(pageSize) || 50)) };

    if (req.user.role_name === 'author') {
      clauses.push('a.author_id = @userId');
      params.userId = req.user.id;
    }
    if (status && ARTICLE_STATUSES.includes(status)) {
      clauses.push('a.status = @status');
      params.status = status;
    }
    if (category) {
      clauses.push('(c.slug = @category OR c.name = @category)');
      params.category = category;
    }
    if (q) {
      clauses.push('(a.title LIKE @q OR a.body LIKE @q)');
      params.q = `%${q}%`;
    }
    const where = clauses.length ? `WHERE ${clauses.join(' AND ')}` : '';
    const count = db.prepare(`SELECT COUNT(*) AS c FROM articles a LEFT JOIN categories c ON c.id = a.category_id ${where}`).get(params).c;
    const rows = db.prepare(`${articleSelect()} ${where} ORDER BY a.id DESC LIMIT @limit OFFSET @offset`).all(params);
    return ok(res, { data: rows, meta: { page: Number(page) || 1, pageSize: Number(pageSize) || 50, total: count } }, 'Content list.');
  } catch (err) {
    return next(err);
  }
}

export async function getContentAuthoring(req, res, next) {
  try {
    const db = getDb();
    const article = db.prepare(`${articleSelect()} WHERE a.id = ?`).get(Number(req.params.id));
    if (!article) return next(apiError(404, 'NOT_FOUND', 'Article not found.'));
    if (!canViewArticle(req.user, article)) return next(apiError(403, 'FORBIDDEN', 'You do not have permission to view this article.'));
    return ok(res, { data: article }, 'Article detail.');
  } catch (err) {
    return next(err);
  }
}

export async function createContentAuthoring(req, res, next) {
  try {
    const db = getDb();
    const body = req.body || {};
    const errors = validate(body, {
      title: { type: 'string', required: true, minLength: 2, maxLength: 200, label: 'Title' },
      body: { type: 'string', maxLength: 100000, label: 'Body' },
      bodyHtml: { type: 'string', maxLength: 200000, label: 'Body HTML' },
      categoryId: { type: 'number', label: 'Category' },
      templateId: { type: 'number', label: 'Template' },
      publishAt: { type: 'string', maxLength: 40, label: 'Publish date' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    let categoryId = null;
    if (body.categoryId) {
      categoryId = db.prepare('SELECT id FROM categories WHERE id = ?').get(Number(body.categoryId));
      if (!categoryId) return next(apiError(400, 'VALIDATION_ERROR', 'Selected category does not exist.'));
      categoryId = categoryId.id;
    }
    let templateId = null;
    if (body.templateId) {
      const tpl = db.prepare('SELECT id FROM page_templates WHERE id = ?').get(Number(body.templateId));
      if (!tpl) return next(apiError(400, 'VALIDATION_ERROR', 'Selected template does not exist.'));
      templateId = tpl.id;
    }

    const title = String(body.title).trim();
    const baseSlug = body.slug ? slugify(body.slug) : slugify(title);
    const slug = uniqueSlug(db, baseSlug);
    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

    const articleId = db.prepare(`INSERT INTO articles (title, slug, body, body_html, tags, status, author_id, category_id, template_id, publish_at, created_at, updated_at)
      VALUES (@title, @slug, @body, @bodyHtml, @tags, 'draft', @authorId, @categoryId, @templateId, @publishAt, @now, @now)`)
      .run({
        title,
        slug,
        body: body.body || '',
        bodyHtml: sanitizeHtml(body.bodyHtml || ''),
        tags: parseTags(body.tags),
        authorId: req.user.id,
        categoryId,
        templateId,
        publishAt: body.publishAt || null,
        now
      }).lastInsertRowid;

    db.prepare(`INSERT INTO rich_text_editor (article_id, content, content_html, version, updated_by, created_at, updated_at)
      VALUES (?, ?, ?, 1, ?, ?, ?)`)
      .run(articleId, body.body || '', sanitizeHtml(body.bodyHtml || ''), req.user.id, now, now);

    audit(db, { actorId: req.user.id, action: 'create_article', entityType: 'articles', entityId: articleId, details: `Created "${title}"` });
    broadcast('content:created', { articleId, title, status: 'draft' });

    const article = db.prepare(`${articleSelect()} WHERE a.id = ?`).get(articleId);
    return ok(res, { data: article }, 'Article created as draft.', 201);
  } catch (err) {
    return next(err);
  }
}

export async function patchContentAuthoring(req, res, next) {
  try {
    const db = getDb();
    const id = Number(req.params.id);
    const article = db.prepare('SELECT * FROM articles WHERE id = ?').get(id);
    if (!article) return next(apiError(404, 'NOT_FOUND', 'Article not found.'));
    if (!canEditArticle(req.user, article)) return next(apiError(403, 'FORBIDDEN', 'You do not have permission to edit this article.'));

    const body = req.body || {};
    const errors = validate(body, {
      title: { type: 'string', minLength: 2, maxLength: 200, label: 'Title' },
      body: { type: 'string', maxLength: 100000, label: 'Body' },
      bodyHtml: { type: 'string', maxLength: 200000, label: 'Body HTML' },
      categoryId: { type: 'number', label: 'Category' },
      templateId: { type: 'number', label: 'Template' },
      publishAt: { type: 'string', maxLength: 40, label: 'Publish date' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const sets = ['updated_at = @now'];
    const params = { id, now };

    if (body.title !== undefined) {
      const title = String(body.title).trim();
      if (title.length < 2) return next(apiError(400, 'VALIDATION_ERROR', 'Title must be at least 2 characters.'));
      sets.push('title = @title');
      params.title = title;
      if (body.slug !== undefined) {
        sets.push('slug = @slug');
        params.slug = uniqueSlug(db, slugify(body.slug), id);
      }
    }
    if (body.body !== undefined) {
      sets.push('body = @body');
      params.body = body.body;
    }
    if (body.bodyHtml !== undefined) {
      sets.push('body_html = @bodyHtml');
      params.bodyHtml = sanitizeHtml(body.bodyHtml);
    }
    if (body.tags !== undefined) {
      sets.push('tags = @tags');
      params.tags = parseTags(body.tags);
    }
    if (body.categoryId !== undefined) {
      const cat = body.categoryId ? db.prepare('SELECT id FROM categories WHERE id = ?').get(Number(body.categoryId)) : null;
      if (body.categoryId && !cat) return next(apiError(400, 'VALIDATION_ERROR', 'Selected category does not exist.'));
      sets.push('category_id = @categoryId');
      params.categoryId = cat ? cat.id : null;
    }
    if (body.templateId !== undefined) {
      const tpl = body.templateId ? db.prepare('SELECT id FROM page_templates WHERE id = ?').get(Number(body.templateId)) : null;
      if (body.templateId && !tpl) return next(apiError(400, 'VALIDATION_ERROR', 'Selected template does not exist.'));
      sets.push('template_id = @templateId');
      params.templateId = tpl ? tpl.id : null;
    }
    if (body.publishAt !== undefined) {
      sets.push('publish_at = @publishAt');
      params.publishAt = body.publishAt || null;
    }

    db.prepare(`UPDATE articles SET ${sets.join(', ')} WHERE id = @id`).run(params);

    if (body.body !== undefined || body.bodyHtml !== undefined) {
      const version = db.prepare('SELECT COALESCE(MAX(version), 0) + 1 AS v FROM rich_text_editor WHERE article_id = ?').get(id).v;
      db.prepare(`INSERT INTO rich_text_editor (article_id, content, content_html, version, updated_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)`)
        .run(id, body.body !== undefined ? body.body : article.body, body.bodyHtml !== undefined ? sanitizeHtml(body.bodyHtml) : article.body_html, version, req.user.id, now, now);
    }

    audit(db, { actorId: req.user.id, action: 'update_article', entityType: 'articles', entityId: id, details: `Updated "${params.title || article.title}"` });
    broadcast('content:updated', { articleId: id, title: params.title || article.title });

    const updated = db.prepare(`${articleSelect()} WHERE a.id = ?`).get(id);
    return ok(res, { data: updated }, 'Article updated.');
  } catch (err) {
    return next(err);
  }
}

export async function deleteContentAuthoring(req, res, next) {
  try {
    const db = getDb();
    const id = Number(req.params.id);
    const article = db.prepare('SELECT * FROM articles WHERE id = ?').get(id);
    if (!article) return next(apiError(404, 'NOT_FOUND', 'Article not found.'));
    if (!canEditArticle(req.user, article)) return next(apiError(403, 'FORBIDDEN', 'You do not have permission to delete this article.'));

    db.prepare('DELETE FROM articles WHERE id = ?').run(id);
    audit(db, { actorId: req.user.id, action: 'delete_article', entityType: 'articles', entityId: id, details: `Deleted "${article.title}"` });
    broadcast('content:deleted', { articleId: id, title: article.title });
    return ok(res, { data: { id, deleted: true } }, 'Article deleted.');
  } catch (err) {
    return next(err);
  }
}
