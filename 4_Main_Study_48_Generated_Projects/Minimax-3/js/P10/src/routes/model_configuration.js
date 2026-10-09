import { Router } from 'express';
import * as cfg from '../modules/model_configuration.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

router.get('/model_configuration', requireAuth, cfg.getConfigs);
router.post('/model_configuration', requireAuth, cfg.postConfigs);
router.patch('/model_configuration/:id', requireAuth, cfg.patchConfigs);
router.delete('/model_configuration/:id', requireAuth, cfg.deleteConfig);

export default router;
