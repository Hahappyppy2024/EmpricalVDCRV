import fs from 'node:fs';
import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import * as fileService from '../services/fileService.js';
import { forbidden, notFound } from '../lib/errors.js';

export function fileRoutes() {
  const router = Router();
  router.use(loadSession, requireAuth);

  router.get('/:id/download', (req, res) => {
    const db = getDb();
    const file = fileService.getFileRecord(db, Number(req.params.id));
    if (!file) throw notFound('File not found');
    if (!fileService.canDownloadFile(db, req.user, file)) throw forbidden('You cannot access this file');
    if (!fs.existsSync(file.path)) throw notFound('File content missing from storage');
    res.download(file.path, file.original_name);
  });

  return router;
}
