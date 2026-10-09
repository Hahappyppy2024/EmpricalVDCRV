import { Router } from 'express';
import { getDb } from '../db/database.js';
import { requireAuth, requireRole } from '../middleware/auth.js';
import { validationError, notFoundError, forbidError } from '../services/errors.js';

const router = Router();

function slugify(s) {
  return String(s).toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

router.get('/content_authoring', requireAuth, (req, res) => {
  const db = getDb();
  const isPrivileged = ['admin', 'editor'].includes(req.user.role_name);
  const articles = isPrivileged
    ? db.prepare('SELECT a.*, u.display_name as author_name, c.name as category_name FROM content_authoring a JOIN users u ON u.id = a.author_id LEFT JOIN categories c ON c.id = a.category_id ORDER BY a.updated_at DESC').all()
    : db.prepare('SELECT a.*, u.display_name as author_name, c.name as category_name FROM content_authoring a JOIN users u ON u.id = a.author_id LEFT JOIN categories c ON c.id = a.category_id WHERE a.author_id = ? ORDER BY a.updated_at DESC').all(req.user.id);
  res.json({ articles: articles.map(serialize) });
});

router.get('/content_authoring/:id', requireAuth, (req, res) => {
  const db = getDb();
  const article = db.prepare('SELECT a.*, u.display_name as author_name, c.name as category_name FROM content_authoring a JOIN users u ON u.id = a.author_id LEFT JOIN categories c ON c.id = a.category_id WHERE a.id = ?').get(Number(req.params.id));
  if (!article) throw notFoundError('Article not found');
  if (req.user.role_name === 'author' && article.author_id !== req.user.id) {
    throw forbidError('Cannot view other authors\' articles');
  }
  res.json({ article: serialize(article) });
});

router.post('/content_authoring', requireAuth, requireRole('admin', 'editor', 'author'), (req, res) => {
  const { title, summary, body, tags, categoryId, templateId } = req.body || {};
  if (!title || typeof title !== 'string' || title.length < 3) throw validationError('Title too short', ['title']);
  const db = getDb();
  const slugBase = slugify(title);
  let slug = slugBase;
  let n = 1;
  while (db.prepare('SELECT id FROM content_authoring WHERE slug = ?').get(slug)) {
    n++;
    slug = `${slugBase}-${n}`;
  }
  const info = db.prepare(
    'INSERT INTO content_authoring (title, slug, summary, body, tags, status, author_id, category_id, template_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
  ).run(title, slug, summary || '', body || '', Array.isArray(tags) ? tags.join(',') : (tags || ''), 'draft', req.user.id, categoryId || null, templateId || null);
  const created = db.prepare('SELECT * FROM content_authoring WHERE id = ?').get(info.lastInsertRowid);
  res.status(201).json({ article: serialize(created), message: 'created' });
});

router.patch('/content_authoring/:id', requireAuth, (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const article = db.prepare('SELECT * FROM content_authoring WHERE id = ?').get(id);
  if (!article) throw notFoundError('Article not found');
  const canEdit = req.user.role_name !== 'author' || article.author_id === req.user.id;
  if (!canEdit) throw forbidError('Cannot edit other authors\' articles');
  const allowed = ['title', 'summary', 'body', 'tags', 'category_id', 'template_id'];
  const updates = [];
  const params = [];
  for (const key of allowed) {
    const bodyKey = key === 'category_id' ? 'categoryId' : (key === 'template_id' ? 'templateId' : key);
    if (req.body && Object.prototype.hasOwnProperty.call(req.body, bodyKey)) {
      let value = req.body[bodyKey];
      if (key === 'tags' && Array.isArray(value)) value = value.join(',');
      updates.push(`${key} = ?`);
      params.push(value);
    }
  }
  if (!updates.length) return res.json({ article: serialize(article) });
  updates.push("updated_at = datetime('now')");
  params.push(id);
  db.prepare(`UPDATE content_authoring SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  const updated = db.prepare('SELECT * FROM content_authoring WHERE id = ?').get(id);
  res.json({ article: serialize(updated), message: 'updated' });
});

router.delete('/content_authoring/:id', requireAuth, (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const article = db.prepare('SELECT * FROM content_authoring WHERE id = ?').get(id);
  if (!article) throw notFoundError('Article not found');
  const canDelete = req.user.role_name !== 'author' || article.author_id === req.user.id;
  if (!canDelete) throw forbidError('Cannot delete other authors\' articles');
  db.prepare('DELETE FROM content_authoring WHERE id = ?').run(id);
  res.json({ message: 'deleted', id });
});

function serialize(a) {
  return {
    id: a.id,
    title: a.title,
    slug: a.slug,
    summary: a.summary,
    body: a.body,
    tags: a.tags ? a.tags.split(',').filter(Boolean) : [],
    status: a.status,
    authorId: a.author_id,
    authorName: a.author_name,
    categoryId: a.category_id,
    categoryName: a.category_name,
    templateId: a.template_id,
    createdAt: a.created_at,
    updatedAt: a.updated_at
  };
}

export default router;
