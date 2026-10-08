import { Router } from 'express';
import multer from 'multer';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import { config } from '../config.js';
import * as fileService from '../services/fileService.js';

const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: config.maxUploadBytes },
});

export function receiptRoutes() {
  const router = Router();
  router.use(loadSession, requireAuth);

  router.get('/', (req, res) => {
    const db = getDb();
    res.json({ ok: true, data: { receipts: fileService.listReceipts(db, req.user) } });
  });

  router.post('/', upload.single('file'), (req, res) => {
    const db = getDb();
    const result = fileService.attachReceipt(db, req.user, {
      file: req.file,
      reportId: req.body.report_id ? Number(req.body.report_id) : null,
      lineId: req.body.line_id ? Number(req.body.line_id) : null,
    });
    res.status(201).json({ ok: true, data: result });
  });

  router.patch('/:id', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const receipt = fileService.updateReceiptMetadata(db, req.user, Number(req.params.id), { originalName: b.original_name });
    res.json({ ok: true, data: receipt });
  });

  return router;
}
