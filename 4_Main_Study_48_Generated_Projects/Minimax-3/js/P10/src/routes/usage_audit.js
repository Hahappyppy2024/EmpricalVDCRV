import { Router } from 'express';
import * as ua from '../modules/usage_and_audit_logs.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

router.get('/usage_and_audit_logs', requireAuth, ua.getUsageAndAuditLogs);
router.post('/usage_and_audit_logs', requireAuth, ua.postUsageAndAuditLogs);
router.patch('/usage_and_audit_logs/:id', requireAuth, ua.patchUsageAndAuditLogs);

export default router;
