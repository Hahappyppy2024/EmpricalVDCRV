import { Router } from 'express';
import { getDb } from '../db/database.js';
import { loadSession, requireAuth } from '../middleware/auth.js';
import * as policyService from '../services/policyService.js';

export function policyRoutes() {
  const router = Router();
  router.use(loadSession, requireAuth);

  router.get('/', (req, res) => {
    const db = getDb();
    const isStaff = req.user.role === 'admin' || req.user.role === 'manager';
    const rules = policyService.listPolicyRules(db, { enabledOnly: !isStaff });
    res.json({ ok: true, data: { rules, types: policyService.POLICY_TYPES } });
  });

  router.post('/', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const rule = policyService.createPolicyRule(db, req.user, {
      code: b.code,
      name: b.name,
      ruleType: b.rule_type,
      categoryId: b.category_id,
      amountThreshold: b.amount_threshold,
      requiresReceipt: b.requires_receipt,
      enabled: b.enabled,
    });
    res.status(201).json({ ok: true, data: rule });
  });

  router.patch('/:id', (req, res) => {
    const db = getDb();
    const b = req.body || {};
    const rule = policyService.updatePolicyRule(db, req.user, Number(req.params.id), {
      name: b.name,
      amount_threshold: b.amount_threshold,
      requires_receipt: b.requires_receipt,
      enabled: b.enabled,
    });
    res.json({ ok: true, data: rule });
  });

  return router;
}
