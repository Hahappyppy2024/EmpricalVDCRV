import { Router } from 'express';
import * as tpl from '../modules/prompt_templates.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

router.get('/prompt_templates', requireAuth, tpl.getTemplates);
router.post('/prompt_templates', requireAuth, tpl.postTemplates);
router.patch('/prompt_templates/:id', requireAuth, tpl.patchTemplates);
router.delete('/prompt_templates/:id', requireAuth, tpl.deleteTemplate);

export default router;
