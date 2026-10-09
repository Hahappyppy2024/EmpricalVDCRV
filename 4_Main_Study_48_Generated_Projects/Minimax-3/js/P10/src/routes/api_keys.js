import { Router } from 'express';
import * as keys from '../modules/api_key_management.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

router.get('/api_key_management', requireAuth, keys.getKeys);
router.post('/api_key_management', requireAuth, keys.postKeys);
router.patch('/api_key_management/:id', requireAuth, keys.patchKeys);
router.delete('/api_key_management/:id', requireAuth, keys.revokeKey);

export default router;
