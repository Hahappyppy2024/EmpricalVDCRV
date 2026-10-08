import { getDb } from '../db/index.js';
import { accountAccessRepo } from '../db/repositories.js';
import { apiError, ok } from '../lib/http.js';
import { hasErrors, validate } from '../lib/validate.js';

const ACTIONS = ['signin', 'signout', 'signup', 'reset', 'other'];

export async function listAccountAccess(req, res, next) {
  try {
    const db = getDb();
    const { page = 1, pageSize = 50 } = req.query;
    const isAdmin = req.user.role_name === 'admin';
    const where = isAdmin ? '' : 'account_access.user_id = @userId';
    const params = { userId: req.user.id };
    const result = db.prepare(`
      SELECT aa.*, u.username
      FROM account_access aa
      JOIN users u ON u.id = aa.user_id
      ${where ? 'WHERE ' + where : ''}
      ORDER BY aa.id DESC
      LIMIT @limit OFFSET @offset
    `).all({ ...params, limit: Math.max(1, Number(pageSize) || 50), offset: (Math.max(1, Number(page) || 1) - 1) * (Math.max(1, Number(pageSize) || 50)) });
    const total = db.prepare(`SELECT COUNT(*) AS c FROM account_access ${where ? 'WHERE ' + where : ''}`).get(params).c;
    return ok(res, { data: result, meta: { page: Number(page) || 1, pageSize: Number(pageSize) || 50, total } }, 'Account access log.');
  } catch (err) {
    return next(err);
  }
}

export async function createAccountAccess(req, res, next) {
  try {
    const body = req.body || {};
    const errors = validate(body, {
      action: { type: 'string', required: true, oneOf: ACTIONS, label: 'Action' },
      details: { type: 'string', maxLength: 500, label: 'Details' }
    });
    if (hasErrors(errors)) {
      return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));
    }
    const record = accountAccessRepo.create({
      user_id: req.user.id,
      action: body.action,
      details: body.details || null,
      ip: req.ip
    });
    return ok(res, { data: record }, 'Account access recorded.', 201);
  } catch (err) {
    return next(err);
  }
}

export async function patchAccountAccess(req, res, next) {
  try {
    if (req.user.role_name !== 'admin') {
      return next(apiError(403, 'FORBIDDEN', 'Only administrators can update account access records.'));
    }
    const id = Number(req.params.id);
    const existing = accountAccessRepo.findById(id);
    if (!existing) {
      return next(apiError(404, 'NOT_FOUND', 'Account access record not found.'));
    }
    const body = req.body || {};
    const record = accountAccessRepo.update(id, {
      details: body.details !== undefined ? body.details : existing.details,
      action: body.action !== undefined ? body.action : existing.action
    });
    return ok(res, { data: record }, 'Account access record updated.');
  } catch (err) {
    return next(err);
  }
}
