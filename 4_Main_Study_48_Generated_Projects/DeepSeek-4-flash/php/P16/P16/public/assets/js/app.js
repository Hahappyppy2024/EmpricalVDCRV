"use strict";

window.P16 = {
  async api(path, opts = {}) {
    const init = Object.assign({ headers: { "Content-Type": "application/json" } }, opts);
    if (init.body && typeof init.body !== "string") {
      init.body = JSON.stringify(init.body);
    }
    const res = await fetch(path, init);
    const isJson = (res.headers.get("content-type") || "").includes("application/json");
    const body = isJson ? await res.json() : null;
    if (!res.ok) {
      const err = new Error((body && body.error) || "Request failed.");
      err.status = res.status;
      err.fields = (body && body.fields) || {};
      throw err;
    }
    return body;
  },

  esc(v) {
    return String(v == null ? "" : v)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  },

  toast(msg, type) {
    const box = document.getElementById("toasts");
    if (!box) return;
    const el = document.createElement("div");
    el.className = "toast " + (type || "ok");
    el.textContent = msg;
    box.appendChild(el);
    setTimeout(() => el.remove(), 4000);
  },

  fmtBytes(n) {
    n = Number(n) || 0;
    const units = ["B", "KB", "MB", "GB", "TB"];
    let i = 0;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return n.toFixed(n >= 100 || i === 0 ? 0 : 1) + " " + units[i];
  },

  fmtUptime(sec) {
    sec = Number(sec) || 0;
    const d = Math.floor(sec / 86400);
    const h = Math.floor((sec % 86400) / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    if (d > 0) return d + "d " + h + "h";
    if (h > 0) return h + "h " + m + "m";
    if (m > 0) return m + "m " + s + "s";
    return s + "s";
  },

  fmtDur(ms) {
    ms = Number(ms) || 0;
    if (ms < 1000) return ms + "ms";
    return (ms / 1000).toFixed(2) + "s";
  },

  pill(value) {
    return '<span class="pill pill-' + P16.esc(value) + '">' + P16.esc(value) + "</span>";
  },

  badge(value) {
    return '<span class="pill pill-' + P16.esc(value) + '">' + P16.esc(value) + "</span>";
  },

  async submitForm(form, { onSuccess, onError } = {}) {
    const data = Object.fromEntries(new FormData(form).entries());
    const action = form.dataset.action || form.action;
    const method = (form.dataset.method || "POST").toUpperCase();
    try {
      const body = await P16.api(action, { method, body: data });
      P16.clearErrors(form);
      P16.toast(body.message || "Done.", "ok");
      if (typeof onSuccess === "function") onSuccess(body, form);
      return body;
    } catch (err) {
      P16.showErrors(form, err.fields || {});
      if (typeof onError === "function") onError(err, form);
      else P16.toast(err.message, "err");
      throw err;
    }
  },

  showErrors(form, fields) {
    P16.clearErrors(form);
    Object.keys(fields || {}).forEach((key) => {
      const input = form.querySelector('[name="' + key + '"]');
      if (input) {
        input.style.borderColor = "#f85149";
        let errEl = document.createElement("div");
        errEl.className = "field-error";
        errEl.textContent = fields[key];
        input.parentElement.appendChild(errEl);
      }
    });
  },

  clearErrors(form) {
    form.querySelectorAll(".field-error").forEach((el) => el.remove());
    form.querySelectorAll("input, select, textarea").forEach((el) => (el.style.borderColor = ""));
  },

  onload(fn) {
    document.addEventListener("DOMContentLoaded", fn);
  },
};
