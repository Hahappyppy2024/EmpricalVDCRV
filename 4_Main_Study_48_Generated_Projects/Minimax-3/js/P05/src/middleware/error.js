export function errorHandler(err, req, res, _next) {
  const status = err.status || 500;
  const payload = {
    error: err.code || 'server_error',
    message: err.message || 'Internal server error'
  };
  if (err.fields) payload.fields = err.fields;
  if (status >= 500) {
    console.error(`[${req.method} ${req.originalUrl}]`, err);
  }
  if (req.accepts('html') && !req.originalUrl.startsWith('/api/')) {
    return res.status(status).render('error', { layout: false, status, message: payload.message, error: payload.error });
  }
  res.status(status).json(payload);
}

export function notFoundHandler(req, res) {
  if (req.accepts('html') && !req.originalUrl.startsWith('/api/')) {
    return res.status(404).render('error', { layout: false, status: 404, message: 'Page not found', error: 'not_found' });
  }
  res.status(404).json({ error: 'not_found', message: 'Not found' });
}
