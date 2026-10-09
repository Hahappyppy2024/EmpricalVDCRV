import { Router } from 'express';
import * as access from '../modules/account_access.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

router.get('/account_access', requireAuth, access.getAccountAccess);
router.post('/account_access', access.postAccountAccess);
router.patch('/account_access/:id', requireAuth, access.patchAccountAccess);

// Convenience endpoints matching the use-case contract for sign-in / sign-up / sign-out.
router.post('/auth/register', access.registerUser);
router.post('/auth/sign-in', access.signIn);
router.post('/auth/sign-out', requireAuth, access.signOut);
router.post('/auth/reset', access.resetPassword);

export default router;
