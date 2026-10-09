import { Router } from 'express';
import * as conv from '../modules/conversation_management.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

router.get('/conversation_management', requireAuth, conv.listConversations);
router.post('/conversation_management', requireAuth, conv.postConversationMgmt);
router.patch('/conversation_management/:id', requireAuth, conv.patchConversationMgmt);

router.get('/conversations/:id', requireAuth, conv.getConversation);
router.post('/conversations/:id/messages', requireAuth, conv.appendMessage);

export default router;
