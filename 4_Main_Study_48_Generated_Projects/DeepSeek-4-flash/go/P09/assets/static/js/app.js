/* P09 Issue Tracking System — vanilla JavaScript client.
 * Handles API integration (ISSUE-12): validation, permission, webhook and
 * upload errors are surfaced as toasts and reported to the backend error log.
 */
(function () {
  "use strict";

  function toast(message, kind) {
    var container = document.querySelector(".toast-container");
    if (!container) {
      container = document.createElement("div");
      container.className = "toast-container";
      document.body.appendChild(container);
    }
    var el = document.createElement("div");
    el.className = "toast " + (kind || "");
    el.textContent = message;
    container.appendChild(el);
    setTimeout(function () {
      el.remove();
    }, 4500);
  }

  /* Report a handled API error state to the backend (best effort). */
  function reportError(operation, code, message) {
    try {
      var body = new URLSearchParams();
      body.set("operation", operation);
      body.set("error_code", code || "unknown");
      body.set("message", (message || "").slice(0, 300));
      fetch("/api/issue/frontend_api_integration_and_errors", {
        method: "POST",
        body: body,
        credentials: "same-origin"
      }).catch(function () {});
    } catch (e) { /* ignore */ }
  }

  /* api(method, url, body): fetch wrapper returning parsed JSON data.
   * On non-ok responses it toasts the deterministic error message, reports it
   * to the error log and returns null. */
  function api(method, url, body, isUpload) {
    var opts = {
      method: method,
      credentials: "same-origin"
    };
    if (body) {
      if (isUpload) {
        opts.body = body; /* FormData */
      } else {
        opts.headers = { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" };
        opts.body = new URLSearchParams(body);
      }
    }
    return fetch(url, opts).then(function (resp) {
      return resp.json().catch(function () {
        return { ok: false, error: { code: "parse_error", message: "invalid API response" } };
      }).then(function (json) {
        if (!resp.ok || json.ok === false) {
          var err = json.error || { code: "unknown", message: "request failed" };
          toast(err.message, "error");
          reportError(url, err.code, err.message);
          return null;
        }
        return json.data;
      });
    }).catch(function (err) {
      toast("Network error: " + err.message, "error");
      reportError(url, "network_error", err.message);
      return null;
    });
  }

  function formDataToObject(form) {
    var out = {};
    new FormData(form).forEach(function (value, key) {
      if (key in out) {
        if (!Array.isArray(out[key])) out[key] = [out[key]];
        out[key].push(value);
      } else {
        out[key] = value;
      }
    });
    return out;
  }

  /* Wire forms marked with data-js-api. data-method overrides POST. */
  document.addEventListener("submit", function (ev) {
    var form = ev.target;
    if (!form.matches || !form.matches("[data-js-api]")) return;
    ev.preventDefault();
    var method = form.getAttribute("data-method") || "POST";
    var url = form.getAttribute("action");
    var data = formDataToObject(form);
    var btn = form.querySelector("button[type=submit]");
    if (btn) { btn.disabled = true; }
    api(method, url, data).then(function (result) {
      if (btn) { btn.disabled = false; }
      if (result === null) return;
      var msg = (result && result.summary) ? result.summary : "Done";
      toast(msg, "ok");
      var redirect = form.getAttribute("data-js-redirect");
      if (redirect) {
        window.location.href = redirect;
      } else {
        window.location.reload();
      }
    });
  });

  /* Wire upload forms marked with data-js-upload (multipart POST). */
  document.addEventListener("submit", function (ev) {
    var form = ev.target;
    if (!form.matches || !form.matches("[data-js-upload]")) return;
    ev.preventDefault();
    var url = form.getAttribute("action");
    var fd = new FormData(form);
    if (!fd.get("file")) {
      toast("A file is required", "error");
      return;
    }
    var btn = form.querySelector("button[type=submit]");
    if (btn) { btn.disabled = true; }
    api("POST", url, fd, true).then(function (result) {
      if (btn) { btn.disabled = false; }
      if (result === null) return;
      var msg = (result && result.summary) ? result.summary : "Upload complete";
      toast(msg, "ok");
      window.location.reload();
    });
  });

  /* Comment edit/delete buttons. */
  document.addEventListener("click", function (ev) {
    var btn = ev.target;
    if (!btn.matches || !btn.matches("[data-edit-comment]")) return;
    var id = btn.getAttribute("data-edit-comment");
    var bodyEl = document.querySelector('[data-comment-body="' + id + '"]');
    var current = bodyEl ? bodyEl.textContent : "";
    var next = prompt("Edit comment:", current);
    if (next === null || next.trim() === "") return;
    api("PATCH", "/api/issue/comments/" + id, { body: next.trim() }).then(function (r) {
      if (r !== null) {
        toast("Comment updated", "ok");
        window.location.reload();
      }
    });
  });

  document.addEventListener("click", function (ev) {
    var btn = ev.target;
    if (!btn.matches || !btn.matches("[data-delete-comment]")) return;
    var id = btn.getAttribute("data-delete-comment");
    if (!window.confirm("Delete this comment?")) return;
    api("DELETE", "/api/issue/comments/" + id, null).then(function (r) {
      if (r !== null) {
        toast("Comment deleted", "ok");
        var el = document.getElementById("comment-" + id);
        if (el) el.remove();
      }
    });
  });

  /* Real-time: connect to the project WebSocket room and surface events. */
  window.connectWS = function (projectID) {
    if (!window.WebSocket) return;
    if (window.__wsRoom === projectID && window.__wsOpen) return;
    window.__wsRoom = projectID;
    try {
      var proto = window.location.protocol === "https:" ? "wss" : "ws";
      var ws = new WebSocket(proto + "://" + window.location.host + "/ws/projects/" + projectID);
      ws.onopen = function () { window.__wsOpen = true; };
      ws.onmessage = function (ev) {
        try {
          var msg = JSON.parse(ev.data);
          toast("Realtime: " + msg.event, "ok");
          setTimeout(function () { window.location.reload(); }, 1200);
        } catch (e) { /* ignore malformed frames */ }
      };
      ws.onclose = function () { window.__wsOpen = false; };
    } catch (e) { /* ws unavailable */ }
  };
})();
