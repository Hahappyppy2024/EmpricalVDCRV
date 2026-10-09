import { Router } from 'express';
import multer from 'multer';
import * as files from '../modules/knowledge_file_upload.js';
import { requireAuth } from '../middleware/auth.js';
import { config } from '../config.js';

const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: config.maxUploadBytes }
});

const router = Router();

router.get('/knowledge_file_upload', requireAuth, files.getFiles);
router.post('/knowledge_file_upload', requireAuth, upload.single('file'), files.postFiles);
router.patch('/knowledge_file_upload/:id', requireAuth, files.patchFiles);
router.delete('/knowledge_file_upload/:id', requireAuth, files.deleteFile);
router.get('/knowledge_file_upload/:id/content', requireAuth, files.readFile);

export default router;
