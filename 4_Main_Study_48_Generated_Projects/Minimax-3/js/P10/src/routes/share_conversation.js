import { Router } from 'express';
import * as share from '../modules/share_conversation.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

router.get('/share_conversation', requireAuth, share.getShares);
router.post('/share_conversation', requireAuth, share.postShares);
router.patch('/share_conversation/:id', requireAuth, share.patchShares);

router.get('/public/share/:token', share.viewShare);

export default router;
