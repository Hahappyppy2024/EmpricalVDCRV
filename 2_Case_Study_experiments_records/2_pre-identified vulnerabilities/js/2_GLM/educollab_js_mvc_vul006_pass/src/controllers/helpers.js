function wantsJson(req) {
  return req.path.startsWith('/api') || req.headers.accept?.includes('application/json');
}

function ok(res, payload) {
  return res.json(payload);
}

function fail(res, status, message) {
  return res.status(status).json({ error: message });
}

module.exports = { wantsJson, ok, fail };
