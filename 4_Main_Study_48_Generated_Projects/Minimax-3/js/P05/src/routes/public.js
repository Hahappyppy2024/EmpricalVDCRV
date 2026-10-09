import { Router } from 'express';
import { getDb } from '../db/database.js';
import { validationError, notFoundError } from '../services/errors.js';

const router = Router();

router.get('/public_site', (req, res) => {
  const db = getDb();
  const { category, q } = req.query;
  const params = [];
  let where = '1=1';
  if (category) { where += ' AND a.tags LIKE ?'; params.push(`%${category}%`); }
  if (q) { where += ' AND (a.title LIKE ? OR a.summary LIKE ? OR a.body LIKE ?)'; params.push(`%${q}%`, `%${q}%`, `%${q}%`); }
  const articles = db.prepare(`
    SELECT a.id, a.title, a.slug, a.summary, a.tags, a.updated_at, c.name as category_name, u.display_name as author_name
    FROM content_authoring a
    LEFT JOIN categories c ON c.id = a.category_id
    JOIN users u ON u.id = a.author_id
    WHERE a.status = 'published' AND ${where}
    ORDER BY a.updated_at DESC
  `).all(...params);
  const categories = db.prepare('SELECT * FROM categories ORDER BY name').all();
  const menus = db.prepare('SELECT * FROM navigation_menus ORDER BY sort_order').all();
  res.json({
    articles: articles.map(serializeArticle),
    categories,
    menus
  });
});

router.get('/public_site/page/:slug', (req, res) => {
  const db = getDb();
  const row = db.prepare(`
    SELECT a.*, u.display_name as author_name, c.name as category_name
    FROM content_authoring a
    JOIN users u ON u.id = a.author_id
    LEFT JOIN categories c ON c.id = a.category_id
    WHERE a.slug = ? AND a.status = 'published'
  `).get(req.params.slug);
  if (!row) throw notFoundError('Page not found');
  res.json({ page: serializeArticle(row, true) });
});

router.post('/public_site', (req, res) => {
  // Site search POST endpoint variant
  const q = req.body?.q || '';
  if (typeof q !== 'string') throw validationError('q required', ['q']);
  const db = getDb();
  const rows = db.prepare(`
    SELECT a.id, a.title, a.slug, a.summary, a.tags, a.updated_at
    FROM content_authoring a
    WHERE a.status = 'published' AND (a.title LIKE ? OR a.summary LIKE ? OR a.body LIKE ?)
    ORDER BY a.updated_at DESC LIMIT 50
  `).all(`%${q}%`, `%${q}%`, `%${q}%`);
  res.json({ results: rows.map(serializeArticle), query: q });
});

function serializeArticle(a, full = false) {
  const base = {
    id: a.id,
    title: a.title,
    slug: a.slug,
    summary: a.summary,
    tags: a.tags ? a.tags.split(',').filter(Boolean) : [],
    categoryName: a.category_name,
    authorName: a.author_name,
    updatedAt: a.updated_at
  };
  if (full) base.body = a.body;
  return base;
}

export default router;
