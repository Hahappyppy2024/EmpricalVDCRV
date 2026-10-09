import { Router } from 'express';
import * as plg from '../modules/tool_plugin_registry.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

router.get('/tool_plugin_registry', requireAuth, plg.getPlugins);
router.post('/tool_plugin_registry', requireAuth, plg.postPlugins);
router.patch('/tool_plugin_registry/:id', requireAuth, plg.patchPlugins);
router.delete('/tool_plugin_registry/:id', requireAuth, plg.deletePlugin);

router.post('/tool_plugin_registry/:id/toggle', requireAuth, plg.setUserPlugin);

export default router;
