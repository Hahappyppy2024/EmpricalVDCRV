import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const viewsDir = path.resolve(__dirname, '..', 'views');

export function notFound(req, res) {
  if (req.path.startsWith('/api/')) {
    return res.status(404).json({ ok: false, error: { code: 'NOT_FOUND', message: 'The requested resource does not exist.' } });
  }
  return res.status(404).sendFile(path.join(viewsDir, '404.html'));
}

export function errorHandler(err, req, res, _next) {
  const status = err.status || 500;
  const code = err.code || (status === 500 ? 'INTERNAL_ERROR' : 'REQUEST_ERROR');
  if (status === 500) {
    // eslint-disable-next-line no-console
    console.error(`[error] ${req.method} ${req.path}:`, err);
  }
  const message = status === 500 ? 'An unexpected error occurred. Please try again later.' : (err.message || 'Request failed.');

  if (req.path.startsWith('/api/')) {
    return res.status(status).json({ ok: false, error: { code, message } });
  }
  if (status === 404) {
    return res.status(404).sendFile(path.join(viewsDir, '404.html'));
  }
  if (status === 401 || status === 403) {
    return res.status(status).sendFile(path.join(viewsDir, 'error.html'));
  }
  return res.status(status).sendFile(path.join(viewsDir, 'error.html'));
}
