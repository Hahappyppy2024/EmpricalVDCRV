import { Router } from 'express';
import * as chat from '../modules/chat_execution.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

router.get('/chat_execution', requireAuth, chat.getChat);
router.post('/chat_execution', requireAuth, chat.postChat);
router.patch('/chat_execution/:id', requireAuth, chat.getExecution);
router.get('/chat_execution/:id', requireAuth, chat.getExecution);

export default router;
