import { validateApiErrorCheck } from '../services/validate.js';
import { requireAuth } from '../middleware/auth.js';
import { asyncRoute, sendJson } from '../services/http.js';

const SCENARIOS = {
  conflict: { status: 409, error: 'conflict', message: 'The requested dates conflict with another booking.' },
  validation: { status: 400, error: 'validation_error', message: 'One or more fields are invalid.', errors: [{ field: 'check_out', message: 'must be after check_in' }] },
  unauthorized: { status: 401, error: 'unauthorized', message: 'Please sign in to continue.' },
  network: { status: 503, error: 'network_error', message: 'The system is temporarily unavailable. Please retry.' },
};

export function registerFrontendApiIntegrationRoutes(app) {
  app.get('/api/hotel/frontend_api_integration_and_errors', asyncRoute(async (req, res) => {
    sendJson(res, 200, { scenarios: Object.keys(SCENARIOS), description: 'POST a scenario to simulate frontend error states.' });
  }));

  app.post('/api/hotel/frontend_api_integration_and_errors', asyncRoute(async (req, res) => {
    const errs = validateApiErrorCheck(req.body);
    if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
    const sc = SCENARIOS[req.body.scenario];
    if (req.body.scenario === 'unauthorized') {
      if (!req.session) return sendJson(res, sc.status, sc);
      return sendJson(res, 200, { message: 'Session is active', ok: true });
    }
    sendJson(res, sc.status, sc);
  }));

  app.patch('/api/hotel/frontend_api_integration_and_errors/:id', asyncRoute(async (req, res) => {
    sendJson(res, 200, { ok: true });
  }));
}