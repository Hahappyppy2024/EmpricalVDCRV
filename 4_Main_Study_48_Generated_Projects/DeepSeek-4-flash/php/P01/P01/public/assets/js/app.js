/* P01 LMS frontend helper. Vanilla JS; no build step. */
window.LMS = (function () {
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function api(url, options) {
    options = options || {};
    options.credentials = 'same-origin';
    var headers = options.headers || {};
    if (options.body && !(options.body instanceof FormData) && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }
    options.headers = headers;
    return fetch(url, options).then(function (res) {
      return res.json().catch(function () {
        return { ok: false, error: { code: 'INTERNAL_ERROR', message: 'The server returned a non-JSON response' } };
      }).then(function (body) {
        if (res.status === 401) {
          window.location.href = '/login';
          throw new Error('Unauthenticated');
        }
        if (body && body.ok !== undefined) {
          body.status = res.status;
          return body;
        }
        return { ok: false, error: { code: 'INTERNAL_ERROR', message: 'Malformed API response' }, status: res.status };
      });
    }).catch(function (err) {
      if (err && err.message === 'Unauthenticated') throw err;
      return { ok: false, error: { code: 'NETWORK_ERROR', message: 'Could not reach the server. Is it running?' }, status: 0 };
    });
  }

  function load(el, promise, render) {
    el.classList.add('is-loading');
    el.innerHTML = '<div class="empty">Loading...</div>';
    return promise.then(function (r) {
      el.classList.remove('is-loading');
      if (!r.ok) {
        el.innerHTML = '<div class="alert alert-error">' + esc(r.error && r.error.message) + ' <span class="muted">[' + esc(r.error && r.error.code) + ']</span></div>';
        return r;
      }
      render(r.data, r);
      return r;
    }).catch(function () {
      el.classList.remove('is-loading');
      el.innerHTML = '<div class="alert alert-error">Request failed while loading data.</div>';
    });
  }

  var ws = null;
  var wsRooms = [];
  var wsHandlers = [];
  var wsTimer = null;
  function ensureWs() {
    if (ws && ws.readyState <= 1) return;
    if (wsTimer) return;
    wsTimer = setTimeout(function () {
      wsTimer = null;
      if (!window.LMS.wsUrl) return;
      try {
        ws = new WebSocket(window.LMS.wsUrl);
        ws.onopen = function () {
          wsRooms.forEach(function (room) {
            ws.send(JSON.stringify({ type: 'join', channel: 'course_' + room }));
          });
        };
        ws.onmessage = function (evt) {
          try {
            var event = JSON.parse(evt.data);
            wsHandlers.forEach(function (h) { if (h(event)) { /* handled */ } });
          } catch (e) { /* ignore malformed frames */ }
        };
        ws.onclose = function () { ws = null; };
        ws.onerror = function () { try { ws.close(); } catch (e) {} };
      } catch (e) { /* websockets unavailable - HTTP refresh fallback */ }
    }, 300);
  }

  function wsJoin(room, handler) {
    if (wsRooms.indexOf(room) === -1) wsRooms.push(room);
    wsHandlers.push(handler);
    ensureWs();
  }

  return { esc: esc, api: api, load: load, wsJoin: wsJoin };
})();
