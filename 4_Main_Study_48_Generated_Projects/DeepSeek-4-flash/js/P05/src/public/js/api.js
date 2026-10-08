export async function api(path, options = {}) {
  const opts = { headers: {}, ...options };
  if (opts.body && typeof opts.body !== 'string' && !(opts.body instanceof FormData)) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(opts.body);
  }
  let res;
  try {
    res = await fetch(path, opts);
  } catch {
    const err = new Error('Network error: the server could not be reached.');
    err.status = 0;
    err.code = 'NETWORK_ERROR';
    throw err;
  }
  let payload = null;
  try {
    payload = await res.json();
  } catch {
    payload = null;
  }
  if (!res.ok) {
    const err = new Error((payload && payload.error && payload.error.message) || 'Request failed.');
    err.status = res.status;
    err.code = (payload && payload.error && payload.error.code) || 'ERROR';
    err.payload = payload;
    throw err;
  }
  return payload;
}
