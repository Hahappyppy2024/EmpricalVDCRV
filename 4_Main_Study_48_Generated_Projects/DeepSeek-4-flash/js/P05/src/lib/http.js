export function apiError(status, code, message) {
  const err = new Error(message);
  err.status = status;
  err.code = code;
  return err;
}

export function ok(res, payload, message, status = 200) {
  const body = { ok: true, message };
  if (payload && typeof payload === 'object' && 'data' in payload) {
    body.data = payload.data;
    if (payload.meta !== undefined) body.meta = payload.meta;
  } else {
    body.data = payload ?? null;
  }
  return res.status(status).json(body);
}

export function parseCookies(req) {
  const header = req.headers.cookie || '';
  const cookies = {};
  for (const part of header.split(';')) {
    const idx = part.indexOf('=');
    if (idx === -1) continue;
    const key = part.slice(0, idx).trim();
    const value = part.slice(idx + 1).trim();
    if (key) cookies[key] = decodeURIComponent(value);
  }
  return cookies;
}

export function publicUser(user) {
  return {
    id: user.id,
    username: user.username,
    email: user.email,
    displayName: user.display_name,
    role: user.role_name,
    roleId: user.role_id,
    status: user.status
  };
}
