import { api, toast } from "./app.js";

document.addEventListener("DOMContentLoaded", init);

function el(tag, attrs = {}, ...children) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs || {})) {
    if (k === "class") node.className = v;
    else if (k.startsWith("on") && typeof v === "function") node.addEventListener(k.slice(2), v);
    else if (v !== null && v !== undefined) node.setAttribute(k, v);
  }
  for (const child of children) {
    if (child === null || child === undefined) continue;
    node.appendChild(typeof child === "string" ? document.createTextNode(child) : child);
  }
  return node;
}

async function init() {
  await Promise.all([loadErrors(), loadSessions(), loadStates(), loadAudit()]);
}

async function loadErrors() {
  try {
    const { items } = await api.get("/chat/errors");
    const tbody = document.getElementById("errors-table");
    tbody.innerHTML = "";
    if (!items.length) tbody.appendChild(el("tr", {}, el("td", { colspan: "6" }, "No errors logged.")));
    for (const e of items.slice(0, 20)) {
      tbody.appendChild(el("tr", {},
        el("td", {}, String(e.id)),
        el("td", {}, String(e.user_id || "-")),
        el("td", {}, e.code),
        el("td", {}, e.message),
        el("td", {}, e.source),
        el("td", {}, new Date(e.created_at).toLocaleString()),
      ));
    }
  } catch (err) { toast(err.message, "error"); }
}

async function loadSessions() {
  try {
    const { items } = await api.get("/chat/accounts/sessions");
    const ul = document.getElementById("admin-sessions");
    ul.innerHTML = "";
    if (!items.length) ul.appendChild(el("li", { class: "muted" }, "No sessions."));
    for (const s of items) {
      ul.appendChild(el("li", {}, `${s.id} · ${s.ip_address} · ${s.revoked ? "revoked" : "active"} · ${new Date(s.created_at).toLocaleString()}`));
    }
  } catch (_e) {}
}

async function loadStates() {
  try {
    const { items } = await api.get("/chat/frontend_api_integration");
    const ul = document.getElementById("frontend-states");
    ul.innerHTML = "";
    if (!items.length) ul.appendChild(el("li", { class: "muted" }, "No states yet."));
    for (const s of items.slice(0, 20)) {
      ul.appendChild(el("li", {}, `${s.context} · ${s.status} · ${new Date(s.updated_at).toLocaleString()}`));
    }
  } catch (_e) {}
}

async function loadAudit() {
  try {
    const { items } = await api.get("/chat/workspaces_and_channels");
    if (!items.length) return;
    const { items: events } = await api.get(`/chat/channel_management/${items[0].id}/audit`);
    const ul = document.getElementById("admin-audit");
    ul.innerHTML = "";
    for (const e of events.slice(0, 20)) {
      ul.appendChild(el("li", {}, `${new Date(e.created_at).toLocaleString()} · ${e.action} · ${e.detail}`));
    }
  } catch (_e) {}
}
