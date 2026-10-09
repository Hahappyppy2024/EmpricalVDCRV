import { Router } from 'express';
import { getDb } from '../db/database.js';
import { requireAuth } from '../middleware/auth.js';
import { validationError, notFoundError, forbidError } from '../services/errors.js';

const router = Router();

const ALLOWED_TAGS = ['strong', 'em', 'u', 's', 'h1', 'h2', 'h3', 'p', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a', 'br'];

function sanitizeHtml(html) {
  if (!html || typeof html !== 'string') return '';
  // Strip script / style / event handlers / javascript: urls
  let s = html.replace(/<\s*(script|style)[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/gi, '');
  s = s.replace(/\son\w+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '');
  s = s.replace(/javascript\s*:/gi, '');
  // Only allow whitelisted tags, drop others
  s = s.replace(/<\/?([a-z][a-z0-9]*)\b[^>]*>/gi, (m, tag) => {
    return ALLOWED_TAGS.includes(tag.toLowerCase()) ? m : '';
  });
  return s;
}

function renderPreview(html) {
  return sanitizeHtml(html);
}

router.get('/rich_text_editor', requireAuth, (req, res) => {
  const db = getDb();
  const articleId = Number(req.query.articleId);
  if (articleId) {
    const rev = db.prepare('SELECT * FROM rich_text_editor WHERE article_id = ? ORDER BY id DESC LIMIT 1').get(articleId);
    if (!rev) return res.json({ revision: null });
    const article = db.prepare('SELECT author_id FROM content_authoring WHERE id = ?').get(articleId);
    if (req.user.role_name === 'author' && article && article.author_id !== req.user.id) throw forbidError('Cannot view other authors\' revisions');
    return res.json({ revision: serialize(rev) });
  }
  const revisions = req.user.role_name === 'author'
    ? db.prepare('SELECT r.* FROM rich_text_editor r JOIN content_authoring a ON a.id = r.article_id WHERE a.author_id = ? ORDER BY r.id DESC').all(req.user.id)
    : db.prepare('SELECT * FROM rich_text_editor ORDER BY id DESC').all();
  res.json({ revisions: revisions.map(serialize) });
});

router.post('/rich_text_editor', requireAuth, (req, res) => {
  const { articleId, bodyHtml, cssClasses } = req.body || {};
  if (!articleId || typeof articleId !== 'number') throw validationError('articleId required', ['articleId']);
  if (typeof bodyHtml !== 'string') throw validationError('bodyHtml required', ['bodyHtml']);
  const db = getDb();
  const article = db.prepare('SELECT * FROM content_authoring WHERE id = ?').get(articleId);
  if (!article) throw notFoundError('Article not found');
  if (req.user.role_name === 'author' && article.author_id !== req.user.id) throw forbidError('Cannot edit other authors\' rich text');
  const sanitized = sanitizeHtml(bodyHtml);
  const previewToken = `prev_${articleId}_${Date.now()}`;
  const info = db.prepare(
    'INSERT INTO rich_text_editor (article_id, body_html, css_classes, preview_token, updated_by) VALUES (?, ?, ?, ?, ?)'
  ).run(articleId, sanitized, cssClasses || 'cms-prose', previewToken, req.user.id);
  db.prepare("UPDATE content_authoring SET body = ?, updated_at = datetime('now') WHERE id = ?").run(sanitized, articleId);
  const rev = db.prepare('SELECT * FROM rich_text_editor WHERE id = ?').get(info.lastInsertRowid);
  res.status(201).json({ revision: serialize(rev), message: 'saved' });
});

router.patch('/rich_text_editor/:id', requireAuth, (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const rev = db.prepare('SELECT * FROM rich_text_editor WHERE id = ?').get(id);
  if (!rev) throw notFoundError('Revision not found');
  const article = db.prepare('SELECT * FROM content_authoring WHERE id = ?').get(rev.article_id);
  if (req.user.role_name === 'author' && article.author_id !== req.user.id) throw forbidError('Cannot edit other authors\' rich text');
  const updates = [];
  const params = [];
  if (typeof req.body?.bodyHtml === 'string') {
    updates.push('body_html = ?');
    params.push(sanitizeHtml(req.body.bodyHtml));
  }
  if (typeof req.body?.cssClasses === 'string') {
    updates.push('css_classes = ?');
    params.push(req.body.cssClasses);
  }
  if (!updates.length) return res.json({ revision: serialize(rev) });
  updates.push('updated_by = ?'); params.push(req.user.id);
  params.push(id);
  db.prepare(`UPDATE rich_text_editor SET ${updates.join(', ')} WHERE id = ?`).run(...params);
  const updated = db.prepare('SELECT * FROM rich_text_editor WHERE id = ?').get(id);
  res.json({ revision: serialize(updated), message: 'updated' });
});

router.get('/rich_text_editor/preview/:token', (req, res) => {
  const db = getDb();
  const rev = db.prepare('SELECT * FROM rich_text_editor WHERE preview_token = ?').get(req.params.token);
  if (!rev) throw notFoundError('Preview not found');
  res.set('Content-Type', 'text/html');
  res.send(`<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/css/styles.css"></head><body><div class="${rev.css_classes}">${renderPreview(rev.body_html)}</div></body></html>`);
});

function serialize(r) {
  return {
    id: r.id,
    articleId: r.article_id,
    bodyHtml: r.body_html,
    cssClasses: r.css_classes,
    previewToken: r.preview_token,
    updatedBy: r.updated_by,
    createdAt: r.created_at
  };
}

export default router;
