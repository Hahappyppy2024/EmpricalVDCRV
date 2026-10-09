// Lightweight client helpers shared across pages.
(function () {
  const App = window.App || {};
  App.api = {};

  function parseJson(text) {
    try {
      return JSON.parse(text);
    } catch (err) {
      throw new Error('Invalid JSON response');
    }
  }

  App.api.request = async function request(path, options) {
    const opts = options || {};
    const init = {
      method: opts.method || 'GET',
      credentials: 'same-origin',
      headers: Object.assign({ 'Accept': 'application/json' }, opts.headers || {})
    };
    if (opts.body !== undefined) {
      if (opts.body instanceof FormData) {
        init.body = opts.body;
      } else {
        init.headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(opts.body);
      }
    }
    const response = await fetch(path, init);
    const text = await response.text();
    const payload = text ? parseJson(text) : {};
    if (!response.ok) {
      const message = payload && payload.error && payload.error.message
        ? payload.error.message
        : `Request failed (${response.status})`;
      const error = new Error(message);
      error.status = response.status;
      error.payload = payload;
      throw error;
    }
    return payload;
  };

  App.api.get = function get(path) {
    return App.api.request(path);
  };

  App.api.post = function post(path, body) {
    return App.api.request(path, { method: 'POST', body });
  };

  App.api.patch = function patch(path, body) {
    return App.api.request(path, { method: 'PATCH', body });
  };

  App.api.del = function del(path) {
    return App.api.request(path, { method: 'DELETE' });
  };

  App.status = function status(node, message, type) {
    if (!node) return;
    node.textContent = message || '';
    node.classList.remove('is-error', 'is-success');
    if (type === 'error') node.classList.add('is-error');
    if (type === 'success') node.classList.add('is-success');
  };

  App.escapeHtml = function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
  };

  App.formatDate = function formatDate(value) {
    if (!value) return '';
    try {
      return new Date(value).toLocaleString();
    } catch (e) {
      return value;
    }
  };

  document.addEventListener('submit', async function (event) {
    const form = event.target;
    if (form && form.matches('form[data-action="signout"]')) {
      event.preventDefault();
      try {
        await App.api.post('/api/data/account_access', { mode: 'signout' });
      } catch (err) {
        console.warn('Sign out failed', err);
      }
      window.location.href = '/login';
    }
  });

  window.App = App;
})();
