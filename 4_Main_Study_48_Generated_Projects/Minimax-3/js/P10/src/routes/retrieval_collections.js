import { Router } from 'express';
import * as rc from '../modules/retrieval_collection.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

router.get('/retrieval_collection', requireAuth, rc.getCollections);
router.post('/retrieval_collection', requireAuth, rc.postCollections);
router.patch('/retrieval_collection/:id', requireAuth, rc.patchCollections);
router.delete('/retrieval_collection/:id', requireAuth, rc.deleteCollection);

router.get('/retrieval_collection/:id', requireAuth, rc.getCollection);
router.post('/retrieval_collection/:id/files', requireAuth, rc.attachFile);
router.delete('/retrieval_collection/:id/files/:file_id', requireAuth, rc.detachFile);
router.post('/retrieval_collection/:id/search', requireAuth, rc.searchCollection);

export default router;
