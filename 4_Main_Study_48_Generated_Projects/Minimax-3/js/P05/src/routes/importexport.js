import { Router } from 'express';
import { existsSync, writeFileSync, readFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { getDb } from '../db/database.js';
import { requireAuth, requireRole } from '../middleware/auth.js';
import { validationError, notFoundError } from '../services/errors.js';

const router = Router();
const UPLOAD_DIR = process.env.UPLOAD_DIR || './uploads';

router.get('/import_export', requireAuth, requireRole('admin'), (req, res) => {
  const db = getDb();
  const jobs = db.prepare('SELECT * FROM import_export ORDER BY id DESC').all();
  res.json({ jobs });
});

router.post('/import_export', requireAuth, requireRole('admin'), (req, res) => {
  const { jobType, payload } = req.body || {};
  if (!['export', 'import'].includes(jobType)) throw validationError('jobType must be export or import', ['jobType']);
  const db = getDb();
  let resultCount = 0;
  let filePath = null;
  if (jobType === 'export') {
    const articles = db.prepare('SELECT * FROM content_authoring').all();
    const settings = db.prepare('SELECT * FROM plugin_settings_panel').all();
    const templates = db.prepare('SELECT * FROM page_templates').all();
    const payloadOut = {
      kind: 'cms.export.v1',
      generatedAt: new Date().toISOString(),
      articles: articles.map(a => ({
        title: a.title, slug: a.slug, summary: a.summary, body: a.body, tags: a.tags,
        status: a.status, categoryId: a.category_id, templateId: a.template_id
      })),
      settings,
      templates
    };
    filePath = join(UPLOAD_DIR, `export-${Date.now()}.json`);
    writeFileSync(filePath, JSON.stringify(payloadOut, null, 2));
    resultCount = articles.length;
  } else {
    if (!payload) throw validationError('payload required for import', ['payload']);
    if (payload.kind !== 'cms.export.v1') throw validationError('Unsupported import payload kind', ['payload']);
    const insert = db.prepare(`
      INSERT INTO content_authoring (title, slug, summary, body, tags, status, author_id, category_id, template_id)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    `);
    const tx = db.transaction((items) => {
      let n = 0;
      for (const a of items) {
        const slug = a.slug + '-imp-' + (n + 1);
        insert.run(a.title, slug, a.summary || '', a.body || '', a.tags || '', a.status || 'draft', req.user.id, a.categoryId || null, a.templateId || null);
        n++;
      }
      return n;
    });
    resultCount = tx(payload.articles || []);
    filePath = null;
  }
  const info = db.prepare(
    'INSERT INTO import_export (job_type, status, payload_json, file_path, requested_by, result_count, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
  ).run(jobType, 'completed', JSON.stringify({ size: resultCount }), filePath, req.user.id, resultCount, new Date().toISOString().replace('T', ' ').substring(0, 19));
  const job = db.prepare('SELECT * FROM import_export WHERE id = ?').get(info.lastInsertRowid);
  res.status(201).json({ job, message: 'completed', resultCount, filePath });
});

router.get('/import_export/download/:id', requireAuth, requireRole('admin'), (req, res) => {
  const id = Number(req.params.id);
  const db = getDb();
  const job = db.prepare('SELECT * FROM import_export WHERE id = ?').get(id);
  if (!job || job.job_type !== 'export' || !job.file_path) throw notFoundError('Export file not available');
  if (!existsSync(job.file_path)) throw notFoundError('File missing on disk');
  res.download(job.file_path);
});

export default router;
