import { getDb } from '../db/index.js';
import { apiError, ok } from '../lib/http.js';
import { hasErrors, validate } from '../lib/validate.js';

export const ERROR_KINDS = ['validation', 'preview', 'missing_page', 'permission', 'network', 'server', 'unknown'];

function logSelect() {
  return `
    SELECT e.*, u.username AS user_username
    FROM frontend_api_integration_and_errors e
    LEFT JOIN users u ON u.id = e.user_id`;
}

export async function listFrontendApi(req, res, next) {
  try {
    const db = getDb();
    const { kind, resolved, page = 1, pageSize = 50 } = req.query;
    const params = { limit: Math.max(1, Number(pageSize) || 50), offset: (Math.max(1, Number(page) || 1) - 1) * (Math.max(1, Number(pageSize) || 50)) };
    const clauses = [];
    if (req.user.role_name !== 'admin') {
      clauses.push('(e.user_id = @userId OR e.user_id IS NULL)');
      params.userId = req.user.id;
    }
    if (kind && ERROR_KINDS.includes(kind)) {
      clauses.push('e.kind = @kind');
      params.kind = kind;
    }
    if (resolved === '1' || resolved === '0') {
      clauses.push('e.resolved = @resolved');
      params.resolved = Number(resolved);
    }
    const where = clauses.length ? `WHERE ${clauses.join(' AND ')}` : '';
    const total = db.prepare(`SELECT COUNT(*) AS c FROM frontend_api_integration_and_errors e ${where}`).get(params).c;
    const rows = db.prepare(`${logSelect()} ${where} ORDER BY e.id DESC LIMIT @limit OFFSET @offset`).all(params);
    return ok(res, { data: rows, meta: { page: Number(page) || 1, pageSize: Number(pageSize) || 50, total } }, 'Frontend API integration and error log.');
  } catch (err) {
    return next(err);
  }
}

export async function createFrontendApi(req, res, next) {
  try {
    const db = getDb();
    const body = req.body || {};
    const errors = validate(body, {
      kind: { type: 'string', required: true, oneOf: ERROR_KINDS, label: 'Kind' },
      statusCode: { type: 'number', label: 'Status code' },
      context: { type: 'string', maxLength: 200, label: 'Context' },
      message: { type: 'string', required: true, maxLength: 500, label: 'Message' },
      url: { type: 'string', maxLength: 500, label: 'URL' }
    });
    if (hasErrors(errors)) return next(apiError(400, 'VALIDATION_ERROR', Object.values(errors)[0]));

    const id = db.prepare(`INSERT INTO frontend_api_integration_and_errors (kind, status_code, context, message, url, user_id, resolved)
      VALUES (?, ?, ?, ?, ?, ?, 0)`)
      .run(body.kind, body.statusCode ?? null, body.context || null, body.message, body.url || null, req.user.id).lastInsertRowid;
    const record = db.prepare(`${logSelect()} WHERE e.id = ?`).get(id);
    return ok(res, { data: record }, 'Frontend API event recorded.', 201);
  } catch (err) {
    return next(err);
  }
}

export async function patchFrontendApi(req, res, next) {
  try {
    const db = getDb();
    const id = Number(req.params.id);
    const record = db.prepare('SELECT * FROM frontend_api_integration_and_errors WHERE id = ?').get(id);
    if (!record) return next(apiError(404, 'NOT_FOUND', 'Frontend API event not found.'));

    const isOwnerOrAdmin = req.user.role_name === 'admin' || record.user_id === req.user.id;
    if (!isOwnerOrAdmin) return next(apiError(403, 'FORBIDDEN', 'You do not have permission to update this event.'));

    const body = req.body || {};
    if (body.resolved !== undefined) {
      if (![0, 1].includes(Number(body.resolved))) {
        return next(apiError(400, 'VALIDATION_ERROR', 'resolved must be 0 or 1.'));
      }
      db.prepare('UPDATE frontend_api_integration_and_errors SET resolved = ? WHERE id = ?').run(Number(body.resolved), id);
    }
    if (body.message !== undefined) {
      if (typeof body.message !== 'string' || body.message.length > 500) {
        return next(apiError(400, 'VALIDATION_ERROR', 'Message must be a string of at most 500 characters.'));
      }
      db.prepare('UPDATE frontend_api_integration_and_errors SET message = ? WHERE id = ?').run(body.message, id);
    }
    const updated = db.prepare(`${logSelect()} WHERE e.id = ?`).get(id);
    return ok(res, { data: updated }, 'Frontend API event updated.');
  } catch (err) {
    return next(err);
  }
}

// ---- Diagnostics endpoints (deterministic controlled error states) ----

export async function diagnosticValidation(_req, _res, next) {
  return next(apiError(400, 'VALIDATION_ERROR', 'Title is required. Body must be between 1 and 100000 characters.'));
}

export async function diagnosticNotFound(_req, _res, next) {
  return next(apiError(404, 'NOT_FOUND', 'The requested resource does not exist.'));
}

export async function diagnosticForbidden(_req, _res, next) {
  return next(apiError(403, 'FORBIDDEN', 'You do not have permission to perform this action.'));
}

export async function diagnosticServerError(_req, _res, next) {
  return next(apiError(500, 'INTERNAL_ERROR', 'An unexpected error occurred. Please try again later.'));
}

export async function diagnosticPreview(_req, res, next) {
  try {
    const db = getDb();
    const payload = {
      preview: true,
      renderedAt: new Date().toISOString(),
      sample: '<p>This is a <strong>preview</strong> of the rendered rich text output.</p><h2>Heading level two</h2><ul><li>Bullet one</li><li>Bullet two</li></ul>'
    };
    return ok(res, { data: payload }, 'Preview generated.');
  } catch (err) {
    return next(err);
  }
}
