import { getDb } from '../db/index.js';
import { apiError, ok } from '../lib/http.js';
import { hasErrors, validate } from '../lib/validate.js';
import { flushScheduled } from '../services/publishing.js';

function publicSettings(db) {
  const rows = db.prepare(`SELECT key, value FROM plugin_settings_panel
    WHERE key IN ('site_name', 'site_tagline', 'allow_comments', 'moderate_comments', 'maintenance_mode')`).all();
  const out = {};
  for (const r of rows) out[r.key] = r.value;
  return out;
}

function publicNav(db) {
  return db.prepare('SELECT label, url FROM nav_menus ORDER BY position, id').all();
}

function publicArticles(db, { category, q, tag, limit = 20, offset = 0 } = {}) {
  const clauses = ["a.status = 'published'"];
  const params = { limit: Math.max(1, Number(limit) || 20), offset: Math.max(0, Number(offset) || 0) };
  if (category) {
    clauses.push('(c.slug = @category OR c.name = @category)');
    params.category = category;
  }
  if (tag) {
    clauses.push('a.tags LIKE @tag');
    params.tag = `%"${tag}"%`;
  }
  if (q) {
    clauses.push('(a.title LIKE @q OR a.body LIKE @q OR a.tags LIKE @q)');
    params.q = `%${q}%`;
  }
  const where = `WHERE ${clauses.join(' AND ')}`;
  const total = db.prepare(`SELECT COUNT(*) AS c FROM articles a LEFT JOIN categories c ON c.id = a.category_id ${where}`).get(params).c;
  const rows = db.prepare(`
    SELECT a.id, a.title, a.slug, a.body_html, a.tags, a.published_at,
           u.username AS author_username, u.display_name AS author_display_name,
           c.name AS category_name, c.slug AS category_slug,
           '/api/cms/media_library/' || m.id || '/file' AS featured_media_url, m.alt_text AS featured_media_alt
    FROM articles a
    LEFT JOIN users u ON u.id = a.author_id
    LEFT JOIN categories c ON c.id = a.category_id
    LEFT JOIN media_library m ON m.id = a.featured_media_id
    ${where}
    ORDER BY a.published_at DESC, a.id DESC LIMIT @limit OFFSET @offset
  `).all(params);
  return { rows, total };
}

function categories(db) {
  return db.prepare(`
    SELECT c.id, c.name, c.slug, c.description,
           (SELECT COUNT(*) FROM articles a WHERE a.category_id = c.id AND a.status = 'published') AS article_count
    FROM categories c ORDER BY c.name
  `).all();
}

export async function getPublicSite(req, res, next) {
  try {
    const db = getDb();
    flushScheduled(db);
    const { category, q, tag, limit, offset } = req.query;
    const articles = publicArticles(db, { category, q, tag, limit, offset });
    return ok(res, {
      data: {
        site: publicSettings(db),
        nav: publicNav(db),
        categories: categories(db),
        articles: articles.rows,
        meta: { total: articles.total }
      }
    }, 'Public site data.');
  } catch (err) {
    return next(err);
  }
}

export async function createPublicSite(req, res, next) {
  try {
    const db = getDb();
    const body = req.body || {};
    const errors = validate(body, {
      articleId: { type: 'number', label: 'Article' },
      visitorName: { type: 'string', maxLength: 100, label: 'Visitor name' },
      action: { type: 'string', maxLength: 50, label: 'Action' },
      details: { type: 'string', maxLength: 500, label: 'Details' },
      referrer: { type: 'string', maxLength: 500, label: 'Referrer' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    if (body.articleId) {
      const article = db.prepare('SELECT id FROM articles WHERE id = ? AND status = \'published\'').get(Number(body.articleId));
      if (!article) return next(apiError(404, 'NOT_FOUND', 'Published article not found.'));
    }

    const recordId = db.prepare(`INSERT INTO public_site (visitor_name, visitor_ip, action, article_id, referrer, details)
      VALUES (?, ?, ?, ?, ?, ?)`)
      .run(body.visitorName || null, req.ip || null, body.action || 'view', body.articleId ? Number(body.articleId) : null, body.referrer || null, body.details || null).lastInsertRowid;
    const record = db.prepare('SELECT * FROM public_site WHERE id = ?').get(recordId);
    return ok(res, { data: record }, 'Page view recorded.', 201);
  } catch (err) {
    return next(err);
  }
}

export async function patchPublicSite(req, res, next) {
  try {
    if (req.user.role_name !== 'admin') {
      return next(apiError(403, 'FORBIDDEN', 'Only administrators can update public site records.'));
    }
    const db = getDb();
    const id = Number(req.params.id);
    const record = db.prepare('SELECT * FROM public_site WHERE id = ?').get(id);
    if (!record) return next(apiError(404, 'NOT_FOUND', 'Public site record not found.'));
    const body = req.body || {};
    db.prepare('UPDATE public_site SET details = COALESCE(@details, details), action = COALESCE(@action, action) WHERE id = @id')
      .run({ details: body.details, action: body.action, id });
    const updated = db.prepare('SELECT * FROM public_site WHERE id = ?').get(id);
    return ok(res, { data: updated }, 'Public site record updated.');
  } catch (err) {
    return next(err);
  }
}

// ---- Public API (visitor-facing) ----

export async function publicListArticles(req, res, next) {
  try {
    const db = getDb();
    flushScheduled(db);
    const { category, q, tag, limit, offset } = req.query;
    const articles = publicArticles(db, { category, q, tag, limit, offset });
    return ok(res, { data: articles.rows, meta: { total: articles.total, category, q, tag } }, 'Published articles.');
  } catch (err) {
    return next(err);
  }
}

export async function publicGetArticle(req, res, next) {
  try {
    const db = getDb();
    flushScheduled(db);
    const article = db.prepare(`
      SELECT a.id, a.title, a.slug, a.body, a.body_html, a.tags, a.published_at, a.updated_at,
             u.username AS author_username, u.display_name AS author_display_name,
             c.name AS category_name, c.slug AS category_slug,
             t.name AS template_name,
             '/api/cms/media_library/' || m.id || '/file' AS featured_media_url, m.alt_text AS featured_media_alt,
             (SELECT COUNT(*) FROM comments cm WHERE cm.article_id = a.id AND cm.status = 'approved') AS approved_comments
      FROM articles a
      LEFT JOIN users u ON u.id = a.author_id
      LEFT JOIN categories c ON c.id = a.category_id
      LEFT JOIN page_templates t ON t.id = a.template_id
      LEFT JOIN media_library m ON m.id = a.featured_media_id
      WHERE a.slug = ? AND a.status = 'published'
    `).get(String(req.params.slug));
    if (!article) return next(apiError(404, 'NOT_FOUND', 'Published article not found.'));
    return ok(res, { data: article }, 'Published article.');
  } catch (err) {
    return next(err);
  }
}

export async function publicListCategories(req, res, next) {
  try {
    const db = getDb();
    return ok(res, { data: categories(db) }, 'Categories.');
  } catch (err) {
    return next(err);
  }
}

export async function publicSearch(req, res, next) {
  return publicListArticles(req, res, next);
}

export async function publicSettingsRoute(req, res, next) {
  try {
    const db = getDb();
    return ok(res, { data: { site: publicSettings(db), nav: publicNav(db) } }, 'Public settings.');
  } catch (err) {
    return next(err);
  }
}
