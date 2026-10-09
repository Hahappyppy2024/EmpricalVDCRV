import express from 'express';
import { db } from '../db/index.js';
import { ok, fail } from '../middleware/http.js';
import { recordAudit } from '../services/userService.js';

const router = express.Router();

function ensureAdminOrManager(session) {
  const role = session?.user?.role;
  if (role !== 'admin' && role !== 'manager') {
    return false;
  }
  return true;
}

router.get('/policy_rules', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  if (!ensureAdminOrManager(session)) return fail(res, 'Admin or manager role required', 403, 'forbidden');
  const rules = db.prepare(`
    SELECT pr.*, c.name AS category_name, c.code AS category_code, u.username AS creator_username
    FROM policy_rules pr
    LEFT JOIN categories c ON c.id = pr.category_id
    LEFT JOIN users u ON u.id = pr.created_by
    ORDER BY pr.id ASC
  `).all();
  const categories = db.prepare('SELECT id, name, code FROM categories ORDER BY name').all();
  return ok(res, { rules, categories });
});

router.post('/policy_rules', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  if (session.user.role !== 'admin') return fail(res, 'Admin role required', 403, 'forbidden');
  const { name, description, categoryCode, maxAmount, requireReceiptAbove, blockWhenExceeded, active } = req.body || {};
  if (!name) return fail(res, 'name is required', 400, 'validation_error');
  const cat = categoryCode ? db.prepare('SELECT id FROM categories WHERE code = ?').get(String(categoryCode)) : null;
  if (categoryCode && !cat) return fail(res, 'Unknown category code', 400, 'validation_error');
  try {
    const info = db.prepare(`
      INSERT INTO policy_rules (name, description, category_id, max_amount, require_receipt_above, block_when_exceeded, active, created_by)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    `).run(
      name,
      description || null,
      cat ? cat.id : null,
      maxAmount == null ? null : Number(maxAmount),
      Number(requireReceiptAbove || 0),
      blockWhenExceeded ? 1 : 0,
      active === false ? 0 : 1,
      session.user.id
    );
    recordAudit(session.user.id, 'policy_rule', info.lastInsertRowid, 'create', { name });
    return ok(res, { id: info.lastInsertRowid }, 201);
  } catch (e) {
    return fail(res, e.message || 'Could not create policy', 400, 'validation_error');
  }
});

router.patch('/policy_rules/:id', express.json(), (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  if (session.user.role !== 'admin') return fail(res, 'Admin role required', 403, 'forbidden');
  const id = Number(req.params.id);
  const rule = db.prepare('SELECT * FROM policy_rules WHERE id = ?').get(id);
  if (!rule) return fail(res, 'Not found', 404, 'not_found');
  const { description, maxAmount, requireReceiptAbove, blockWhenExceeded, active, categoryCode } = req.body || {};
  const updates = [];
  const values = [];
  if (description !== undefined) { updates.push('description = ?'); values.push(description); }
  if (maxAmount !== undefined) { updates.push('max_amount = ?'); values.push(maxAmount == null ? null : Number(maxAmount)); }
  if (requireReceiptAbove !== undefined) { updates.push('require_receipt_above = ?'); values.push(Number(requireReceiptAbove)); }
  if (blockWhenExceeded !== undefined) { updates.push('block_when_exceeded = ?'); values.push(blockWhenExceeded ? 1 : 0); }
  if (active !== undefined) { updates.push('active = ?'); values.push(active ? 1 : 0); }
  if (categoryCode !== undefined) {
    const cat = categoryCode ? db.prepare('SELECT id FROM categories WHERE code = ?').get(String(categoryCode)) : null;
    if (categoryCode && !cat) return fail(res, 'Unknown category code', 400, 'validation_error');
    updates.push('category_id = ?'); values.push(cat ? cat.id : null);
  }
  if (!updates.length) return fail(res, 'Nothing to update', 400, 'validation_error');
  updates.push("updated_at = datetime('now')");
  values.push(id);
  db.prepare(`UPDATE policy_rules SET ${updates.join(', ')} WHERE id = ?`).run(...values);
  recordAudit(session.user.id, 'policy_rule', id, 'update', req.body);
  return ok(res, { updated: true });
});

router.get('/policy_rules/_preview/:reportId', (req, res) => {
  const session = req.session;
  if (!session) return fail(res, 'Authentication required', 401, 'unauthenticated');
  if (!ensureAdminOrManager(session)) return fail(res, 'Admin or manager role required', 403, 'forbidden');
  const report = db.prepare('SELECT * FROM expense_reports WHERE id = ?').get(Number(req.params.reportId));
  if (!report) return fail(res, 'Not found', 404, 'not_found');
  const rules = db.prepare('SELECT * FROM policy_rules WHERE active = 1').all();
  const lines = db.prepare(`
    SELECT el.amount, el.category_id, c.code AS category_code
    FROM expense_lines el
    LEFT JOIN categories c ON c.id = el.category_id
    WHERE el.report_id = ?
  `).all(report.id);
  const violations = [];
  const warnings = [];
  for (const line of lines) {
    for (const r of rules) {
      if (r.category_id && r.category_id !== line.category_id) continue;
      if (r.max_amount != null && line.amount > r.max_amount) {
        (r.block_when_exceeded ? violations : warnings).push({ rule: r.name, lineAmount: line.amount, cap: r.max_amount });
      }
    }
  }
  return ok(res, { violations, warnings });
});

export default router;
