import { getDb } from '../db/database.js';
import { badRequest, forbidden, notFound } from '../lib/errors.js';
import { writeAudit } from './auditService.js';

export const POLICY_TYPES = ['max_report_amount', 'requires_receipt', 'category_limit', 'approval_threshold'];

export function listPolicyRules(db, { enabledOnly = false } = {}) {
  const sql = `
    SELECT r.*, c.name AS category_name
    FROM policy_rules r LEFT JOIN categories c ON c.id = r.category_id
    ${enabledOnly ? 'WHERE r.enabled = 1' : ''}
    ORDER BY r.id
  `;
  return db.prepare(sql).all();
}

export function getPolicyRule(db, id) {
  const rule = db.prepare('SELECT * FROM policy_rules WHERE id = ?').get(id);
  if (!rule) throw notFound('Policy rule not found');
  return rule;
}

export function createPolicyRule(db, user, { code, name, ruleType, categoryId, amountThreshold, requiresReceipt, enabled }) {
  if (user.role !== 'admin' && user.role !== 'manager') throw forbidden('Only admin and manager can manage policy rules');
  if (!code || !String(code).trim()) throw badRequest('code is required');
  if (!name || !String(name).trim()) throw badRequest('name is required');
  if (!POLICY_TYPES.includes(ruleType)) throw badRequest('Invalid rule_type');
  if (categoryId && !db.prepare('SELECT id FROM categories WHERE id = ?').get(categoryId)) throw badRequest('Invalid category_id');
  if (['max_report_amount', 'category_limit', 'approval_threshold'].includes(ruleType) && amountThreshold === undefined) {
    throw badRequest('amount_threshold is required for this rule type');
  }
  const existing = db.prepare('SELECT id FROM policy_rules WHERE code = ?').get(code);
  if (existing) throw badRequest('A rule with this code already exists');
  const info = db
    .prepare(
      `INSERT INTO policy_rules (code, name, rule_type, category_id, amount_threshold, requires_receipt, enabled, created_by, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))`
    )
    .run(String(code).trim(), String(name).trim(), ruleType, categoryId || null, amountThreshold !== undefined ? Number(amountThreshold) : null, requiresReceipt !== undefined ? (requiresReceipt ? 1 : 0) : null, enabled === undefined ? 1 : enabled ? 1 : 0, user.id);
  writeAudit(db, { actorId: user.id, action: 'policy.created', entityType: 'policy_rule', entityId: info.lastInsertRowid, details: { code } });
  return getPolicyRule(db, info.lastInsertRowid);
}

export function updatePolicyRule(db, user, id, patch) {
  if (user.role !== 'admin' && user.role !== 'manager') throw forbidden('Only admin and manager can manage policy rules');
  const rule = getPolicyRule(db, id);
  const fields = [];
  const params = [];
  if (patch.name !== undefined) {
    if (!String(patch.name).trim()) throw badRequest('name cannot be empty');
    fields.push('name = ?');
    params.push(String(patch.name).trim());
  }
  if (patch.amount_threshold !== undefined) {
    const v = Number(patch.amount_threshold);
    if (!Number.isFinite(v) || v < 0) throw badRequest('amount_threshold must be a non-negative number');
    fields.push('amount_threshold = ?');
    params.push(v);
  }
  if (patch.requires_receipt !== undefined) {
    fields.push('requires_receipt = ?');
    params.push(patch.requires_receipt ? 1 : 0);
  }
  if (patch.enabled !== undefined) {
    fields.push('enabled = ?');
    params.push(patch.enabled ? 1 : 0);
  }
  if (fields.length === 0) throw badRequest('No updatable fields provided');
  fields.push('updated_at = datetime(\'now\')');
  params.push(id);
  db.prepare(`UPDATE policy_rules SET ${fields.join(', ')} WHERE id = ?`).run(...params);
  writeAudit(db, { actorId: user.id, action: 'policy.updated', entityType: 'policy_rule', entityId: id, details: { code: rule.code, patch } });
  return getPolicyRule(db, id);
}

export function enabledRules(db) {
  return db.prepare('SELECT * FROM policy_rules WHERE enabled = 1').all();
}

export function evaluatePolicy(db, report) {
  const warnings = [];
  const rules = enabledRules(db);
  const lines = db.prepare('SELECT * FROM expense_lines WHERE report_id = ?').all(report.id);
  const total = lines.reduce((s, l) => s + l.amount, 0);

  for (const rule of rules) {
    if (rule.rule_type === 'max_report_amount') {
      if (total > rule.amount_threshold) {
        warnings.push({ code: rule.code, severity: 'warning', message: `Report total exceeds the ${rule.amount_threshold} limit` });
      }
    } else if (rule.rule_type === 'approval_threshold') {
      if (total >= rule.amount_threshold) {
        warnings.push({ code: rule.code, severity: 'info', message: `Report total ${total} meets the approval threshold requiring manager approval` });
      }
    } else if (rule.rule_type === 'category_limit') {
      for (const line of lines) {
        if (line.category_id === rule.category_id && line.amount > rule.amount_threshold) {
          warnings.push({ code: rule.code, severity: 'warning', message: `Category line exceeds the ${rule.amount_threshold} category limit` });
        }
      }
    } else if (rule.rule_type === 'requires_receipt') {
      if (rule.requires_receipt) {
        for (const line of lines) {
          if (line.receipt_id === null) {
            const cat = db.prepare('SELECT * FROM categories WHERE id = ?').get(line.category_id);
            if (cat && cat.requires_receipt) {
              warnings.push({ code: rule.code, severity: 'warning', message: `Receipt missing for line: ${line.description}` });
            }
          }
        }
      }
    }
  }
  return warnings;
}

export function policyWarningsForReport(db, report) {
  return evaluatePolicy(db, report);
}
