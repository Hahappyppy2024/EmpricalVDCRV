import { Router } from 'express';
import { getDb } from '../db/database.js';
import { logApiError } from '../services/audit.js';

const router = Router();

// This route simulates frontend API integration and error tracking.
router.get('/frontend_api_integration_and_errors', (req, res) => {
  const db = getDb();
  const items = db.prepare('SELECT * FROM frontend_api_integration_and_errors ORDER BY id DESC LIMIT 100').all();
  res.json({ items });
});

router.post('/frontend_api_integration_and_errors', (req, res) => {
  const { endpoint, method, responseCode, errorClass, message, simulate } = req.body || {};
  if (!endpoint || !method) {
    return res.status(400).json({ error: 'validation_error', message: 'endpoint and method required' });
  }

  // Simulate response states: validation, preview, missing, permission errors.
  if (simulate === 'validation') {
    logApiError({ endpoint, method, code: 422, errorClass: 'validation_error', message: 'Field x is required', userId: req.user?.id });
    return res.status(422).json({ error: 'validation_error', message: 'Simulated validation error', fields: ['x'] });
  }
  if (simulate === 'missing') {
    logApiError({ endpoint, method, code: 404, errorClass: 'not_found', message: 'Resource not found', userId: req.user?.id });
    return res.status(404).json({ error: 'not_found', message: 'Simulated missing page' });
  }
  if (simulate === 'permission') {
    logApiError({ endpoint, method, code: 403, errorClass: 'forbidden', message: 'Permission denied', userId: req.user?.id });
    return res.status(403).json({ error: 'forbidden', message: 'Simulated permission error' });
  }
  if (simulate === 'preview') {
    logApiError({ endpoint, method, code: 200, errorClass: null, message: 'preview ok', userId: req.user?.id });
    return res.json({ status: 'preview_ok', payload: { ok: true, note: 'Simulated preview response' } });
  }
  // Record raw integration call
  logApiError({ endpoint, method, code: responseCode || 200, errorClass: errorClass || null, message: message || null, userId: req.user?.id });
  res.json({ status: 'logged', endpoint, method });
});

export default router;
