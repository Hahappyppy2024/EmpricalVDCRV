(function () {
  'use strict';

  window.CONF = window.CONF || { ws_url: '', app_url: '', user: null };

  class ApiError extends Error {
    constructor(err) {
      super((err && err.message) || 'Request failed.');
      this.code = (err && err.code) || 'unknown_error';
      this.status = (err && err.status) || 0;
      this.details = (err && err.details) || {};
    }
  }

  async function api(method, path, body) {
    const opts = { method: method, headers: {}, credentials: 'same-origin' };
    if (body) {
      if (body instanceof FormData) {
        opts.body = body;
      } else {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
      }
    }
    const res = await fetch(path, opts);
    let json = null;
    try {
      json = await res.json();
    } catch (e) {
      json = null;
    }
    if (!json || json.ok !== true) {
      const err = json && json.error
        ? json.error
        : { code: 'http_' + res.status, status: res.status, message: 'Unexpected HTTP ' + res.status, details: {} };
      throw new ApiError(err);
    }
    return json.data;
  }

  let toastTimer = null;
  function toast(message, type) {
    const el = document.getElementById('toast');
    if (!el) {
      return;
    }
    el.textContent = message;
    el.className = 'toast toast--' + (type || 'info');
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      el.hidden = true;
    }, 3500);
  }

  function clearFormErrors(form) {
    form.querySelectorAll('.form-error').forEach(function (el) {
      el.remove();
    });
    form.querySelectorAll('.field-error').forEach(function (el) {
      el.classList.remove('field-error');
    });
    form.querySelectorAll('[data-field-msg]').forEach(function (el) {
      el.remove();
    });
  }

  function showFormError(form, err) {
    const banner = document.createElement('div');
    banner.className = 'form-error';
    banner.textContent = (err && err.message) ? err.message : 'The request failed.';
    form.prepend(banner);

    const fields = (err && err.details && err.details.fields) || {};
    Object.keys(fields).forEach(function (name) {
      const input = form.querySelector('[name="' + name + '"]');
      if (input) {
        input.classList.add('field-error');
        const msg = document.createElement('div');
        msg.setAttribute('data-field-msg', '');
        msg.className = 'form-error';
        msg.style.border = 'none';
        msg.style.padding = '2px 0 0';
        msg.textContent = fields[name];
        input.parentNode.appendChild(msg);
      }
    });

    if (err && err.code) {
      toast(err.message, 'error');
    }
  }

  document.addEventListener('submit', async function (ev) {
    const form = ev.target.closest('form[data-api-form]');
    if (!form) {
      return;
    }
    ev.preventDefault();

    const method = form.dataset.method || 'POST';
    const isJson = form.dataset.json === 'true';
    let body;
    if (isJson) {
      body = {};
      new FormData(form).forEach(function (value, key) {
        body[key] = value;
      });
    } else {
      body = new FormData(form);
    }

    clearFormErrors(form);
    form.classList.add('is-loading');
    try {
      await api(method, form.action, body);
      if (form.dataset.redirect) {
        window.location.href = form.dataset.redirect;
        return;
      }
      if (form.dataset.refresh === 'true') {
        window.location.reload();
        return;
      }
      toast(form.dataset.successMessage || 'Saved successfully.', 'success');
      if (form.dataset.reset === 'true') {
        form.reset();
      }
    } catch (err) {
      showFormError(form, err);
    } finally {
      form.classList.remove('is-loading');
    }
  });

  // Search forms (submission discovery)
  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function renderResults(target, matches) {
    if (!target) {
      return;
    }
    if (!matches || matches.length === 0) {
      target.innerHTML = '<p class="muted">No matching submissions. Adjust your filters.</p>';
      return;
    }
    const rows = matches.map(function (m) {
      return '<tr>'
        + '<td>' + escapeHtml(m.id) + '</td>'
        + '<td><a href="/papers/' + escapeHtml(m.id) + '">' + escapeHtml(m.title) + '</a></td>'
        + '<td>' + escapeHtml(m.status) + '</td>'
        + '<td>' + escapeHtml(m.author_name || '') + '</td>'
        + '<td>' + escapeHtml(m.created_at || '') + '</td>'
        + '</tr>';
    }).join('');
    target.innerHTML = '<table class="table"><thead><tr>'
      + '<th>ID</th><th>Title</th><th>Status</th><th>Author</th><th>Date</th>'
      + '</tr></thead><tbody>' + rows + '</tbody></table>';
  }

  document.addEventListener('submit', async function (ev) {
    const form = ev.target.closest('form[data-search-form]');
    if (!form) {
      return;
    }
    ev.preventDefault();
    const endpoint = form.dataset.endpoint || '/api/conf/submission_discovery';
    const params = new URLSearchParams(new FormData(form));
    const target = document.querySelector(form.dataset.target || '[data-results]');
    clearFormErrors(form);
    try {
      const data = await api('GET', endpoint + (params.toString() ? '?' + params.toString() : ''));
      renderResults(target, data.matches || data.submissions || []);
    } catch (err) {
      showFormError(form, err);
    }
  });

  // Double-blind views loader
  function renderBlindView(el, view) {
    if (!view) {
      el.innerHTML = '<p class="muted">No view available.</p>';
      return;
    }
    const summary = view.review_summary
      ? '<p>Reviews: <strong>' + escapeHtml(view.review_summary.count) + '</strong> · '
        + 'Avg score: <strong>' + escapeHtml(view.review_summary.average_score) + '</strong> · '
        + 'Avg confidence: <strong>' + escapeHtml(view.review_summary.average_confidence) + '</strong></p>'
      : '';
    const comments = (view.review_comments || []).map(function (c) {
      return '<li>' + escapeHtml(c.comments) + '</li>';
    }).join('');
    const authorName = view.author_name ? '<p>Author: <strong>' + escapeHtml(view.author_name) + '</strong></p>' : '';
    el.innerHTML = '<p><strong>' + escapeHtml(view.title) + '</strong> — ' + escapeHtml(view.status)
      + ' (blind: ' + (view.blind ? 'yes' : 'no') + ')</p>'
      + '<p>' + escapeHtml(view.abstract) + '</p>'
      + authorName
      + summary
      + (comments ? '<h3>Review comments</h3><ul class="list">' + comments + '</ul>' : '');
  }

  async function loadBlindViews() {
    const el = document.querySelector('[data-blind-view]');
    if (!el) {
      return;
    }
    const submissionId = el.getAttribute('data-submission');
    try {
      const data = await api('GET', '/api/conf/double_blind_views?submission_id=' + encodeURIComponent(submissionId));
      renderBlindView(el, data.view || data);
    } catch (err) {
      el.innerHTML = '<div class="form-error">' + escapeHtml(err.message) + '</div>';
    }
  }
  loadBlindViews();

  // Real-time events: prefer WebSocket; fall back to HTTP polling.
  function handleRealtimeMessage(msg) {
    if (!msg || typeof msg.type !== 'string') {
      return;
    }
    if (msg.type === 'connected' || msg.type === 'pong') {
      return;
    }
    toast('Live: ' + msg.type.replace(/\./g, ' '), 'info');
  }

  function startPolling() {
    let since = 0;
    window.setInterval(async function () {
      try {
        const data = await api('GET', '/api/conf/realtime_events?since=' + since);
        since = data.latest_id || since;
        (data.events || []).forEach(handleRealtimeMessage);
      } catch (e) {
        // transient polling failures are ignored
      }
    }, 3000);
  }

  function connectFeed() {
    if (!window.CONF.ws_url) {
      startPolling();
      return;
    }
    let ws = null;
    try {
      ws = new WebSocket(window.CONF.ws_url);
    } catch (e) {
      startPolling();
      return;
    }
    let fellBack = false;
    const fallbackTimer = window.setTimeout(function () {
      if (ws && ws.readyState !== WebSocket.OPEN) {
        fellBack = true;
        try { ws.close(); } catch (e) { /* noop */ }
        startPolling();
      }
    }, 5000);
    ws.onmessage = function (ev) {
      let msg = null;
      try {
        msg = JSON.parse(ev.data);
      } catch (e) {
        return;
      }
      handleRealtimeMessage(msg);
    };
    ws.onopen = function () {
      window.clearTimeout(fallbackTimer);
    };
    ws.onclose = function () {
      if (!fellBack) {
        window.setTimeout(connectFeed, 4000);
      }
    };
  }
  connectFeed();

  window.ConfApp = { api: api, toast: toast, ApiError: ApiError, escapeHtml: escapeHtml };
})();
