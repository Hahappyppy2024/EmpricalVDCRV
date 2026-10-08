(function () {
  async function api(path, { method = 'GET', body, form } = {}) {
    const opts = { method, headers: {} };
    if (form) {
      opts.body = form;
    } else if (body !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    const res = await fetch(path, opts);
    let data = {};
    try {
      data = await res.json();
    } catch (_) {
      data = {};
    }
    if (!res.ok) {
      const err = new Error((data.error && data.error.message) || 'Request failed');
      err.code = data.error && data.error.code;
      err.status = res.status;
      err.details = data.error && data.error.details;
      throw err;
    }
    return data.data;
  }

  window.API = {
    get: (p) => api(p),
    post: (p, body) => api(p, { method: 'POST', body }),
    postForm: (p, form) => api(p, { method: 'POST', form }),
    patch: (p, body) => api(p, { method: 'PATCH', body }),
    del: (p) => api(p, { method: 'DELETE' }),
    raw: api,
  };
})();
