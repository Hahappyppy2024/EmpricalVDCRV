import { buildReport, toCsv } from '../services/reports.js';
import { recent } from '../services/audit.js';
import { validateAdminReport } from '../services/validate.js';
import { record } from '../services/audit.js';
import { requireRole } from '../middleware/auth.js';
import { asyncRoute, sendJson } from '../services/http.js';

export function registerAdminReportsRoutes(app) {
  app.get('/api/hotel/admin_reports', requireRole('admin'), asyncRoute(async (req, res) => {
    const body = {
      report: req.query.report,
      start_date: req.query.start_date,
      end_date: req.query.end_date,
    };
    const errs = validateAdminReport(body);
    if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
    const result = buildReport(body);
    if (req.query.format === 'csv') {
      res.statusCode = 200;
      res.setHeader('content-type', 'text/csv; charset=utf-8');
      res.setHeader('content-disposition', `attachment; filename="${body.report}.csv"`);
      return res.end(toCsv(result));
    }
    sendJson(res, 200, { ...result, audit: recent(20) });
  }));

  app.post('/api/hotel/admin_reports', requireRole('admin'), asyncRoute(async (req, res) => {
    const errs = validateAdminReport(req.body);
    if (errs.length) return sendJson(res, 400, { error: 'validation_error', errors: errs });
    const result = buildReport(req.body);
    record({ actor: req.session.user, action: `admin.report.${req.body.report}`, targetType: 'report', targetId: null, detail: `${req.body.start_date || ''}..${req.body.end_date || ''}` });
    sendJson(res, 200, result);
  }));

  app.patch('/api/hotel/admin_reports/:id', requireRole('admin'), asyncRoute(async (req, res) => {
    sendJson(res, 200, { ok: true });
  }));
}