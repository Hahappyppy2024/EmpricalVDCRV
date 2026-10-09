import { Router } from 'express';
import * as adm from '../modules/admin_moderation_and_settings.js';
import { requireAuth, requireRole } from '../middleware/auth.js';

const router = Router();

router.get('/admin_moderation_and_settings', requireAuth, requireRole('admin'), adm.getAdminSettings);
router.post('/admin_moderation_and_settings', requireAuth, requireRole('admin'), adm.postAdminSettings);
router.patch('/admin_moderation_and_settings/:id', requireAuth, requireRole('admin'), adm.patchAdminSettings);

router.get('/admin/users', requireAuth, requireRole('admin'), adm.listUsers);
router.get('/admin/settings', requireAuth, requireRole('admin'), adm.getSettings);
router.post('/admin/settings', requireAuth, requireRole('admin'), adm.upsertSetting);
router.get('/admin/blocked_terms', requireAuth, requireRole('admin'), adm.listBlockedTerms);
router.post('/admin/blocked_terms', requireAuth, requireRole('admin'), adm.addBlockedTerm);
router.delete('/admin/blocked_terms/:id', requireAuth, requireRole('admin'), adm.removeBlockedTerm);
router.get('/admin/moderation', requireAuth, requireRole('admin'), adm.listModeration);
router.post('/admin/moderation', requireAuth, requireRole('admin'), adm.moderateUser);

export default router;
