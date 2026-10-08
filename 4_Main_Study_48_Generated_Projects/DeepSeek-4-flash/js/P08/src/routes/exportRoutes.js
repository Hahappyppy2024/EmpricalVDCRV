import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth, requireRole } from '../middleware/auth.js';
import * as fileService from '../services/fileService.js';

export function exportRoutes() {
  const router = Router();
  router.use(loadSession, requireAuth, requireRole('finance', 'admin'));

  router.get('/', (req, res) => {
    const db = getDb();
    res.json({ ok: true, data: { batches: fileService.listExportBatches(db, req.user) } });
  });

  router.post('/', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const reportIds = Array.isArray(b.report_ids) ? b.report_ids.map(Number).filter(Number.isInteger) : null;
    const result = fileService.buildCsvExport(db, req.user, { reportIds });
    res.status(201).json({
      ok: true,
      data: {
        batch: result.batch,
        file: { id: result.file.id, original_name: result.file.original_name, mime_type: result.file.mime_type, size: result.file.size },
        download_url: `/api/files/${result.file.id}/download`,
        csv: result.csv,
      },
    });
  });

  router.patch('/:id', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const batch = fileService.updateExportBatch(db, req.user, Number(req.params.id), { note: b.note, status: b.status });
    res.json({ ok: true, data: batch });
  });

  return router;
}
