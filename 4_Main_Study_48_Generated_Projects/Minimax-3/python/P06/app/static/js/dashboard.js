import { api, postState, reportError, toast } from "./app.js";

const state = {
  user: null,
  workspaceId: null,
  channelId: null,
  channel: null,
  workspace: null,
  ws: null,
  eventId: "",
  connectionId: null,
  typingUsers: new Map(),
};

function $(id) { return document.getElementById(id); }

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

function fmtDate(value) {
  if (!value) return "";
  const d = new Date(value);
  if (isNaN(d.getTime())) return value;
  return d.toLocaleString();
}

document.addEventListener("DOMContentLoaded", init);

async function init() {
  try {
    state.user = await api.get("/chat/accounts/me");
    if (!state.user || state.user.status === "anonymous") {
      window.location.href = "/signin";
      return;
    }
    if (state.user.is_admin) {
      postState("dashboard.admin", "success");
    }
    postState("dashboard", "loading");
    await loadWorkspaces();
    connectWebSocket();
    setupFormHandlers();
    postState("dashboard", "idle");
  } catch (err) {
    toast(err.message, "error");
  }
}

async function loadWorkspaces() {
  const ul = $("workspaces");
  ul.innerHTML = "";
  const { items } = await api.get("/chat/workspaces_and_channels");
  if (!items.length) {
    ul.appendChild(el("li", { class: "empty muted" }, "No workspaces yet."));
  }
  for (const ws of items) {
    const li = el("li", {
      onclick: () => selectWorkspace(ws),
    }, ws.name, el("span", { class: "meta" }, ws.description || ""));
    if (state.workspaceId === ws.id) li.classList.add("active");
    ul.appendChild(li);
  }
  if (items.length) {
    selectWorkspace(items[0]);
  }
}

async function selectWorkspace(ws) {
  state.workspace = ws;
  state.workspaceId = ws.id;
  document.querySelectorAll("#workspaces li").forEach((li) => li.classList.remove("active"));
  const channelsUl = $("channels");
  channelsUl.innerHTML = "";
  try {
    const { items } = await api.get(`/chat/workspaces_and_channels/${ws.id}/channels`);
    for (const ch of items) {
      const li = el("li", {
        onclick: () => selectChannel(ch),
      }, `${ch.name}${ch.is_private ? " 🔒" : ""}`, el("span", { class: "meta" }, ch.topic));
      channelsUl.appendChild(li);
    }
    if (items.length) {
      selectChannel(items[0]);
    }
    await loadMembers();
    await loadInvitations();
    await loadAudit();
  } catch (err) {
    toast(err.message, "error");
  }
}

async function selectChannel(channel) {
  state.channel = channel;
  state.channelId = channel.id;
  $("channel-title").textContent = `#${channel.name}`;
  $("channel-meta").textContent = `${channel.description || "No description"} · topic: ${channel.topic}${channel.archived ? " · archived" : ""}`;
  document.querySelectorAll("#channels li").forEach((li) => li.classList.remove("active"));
  const list = [...document.querySelectorAll("#channels li")];
  const active = list.find((li) => li.textContent.startsWith(channel.name));
  if (active) active.classList.add("active");
  await loadMessages(channel.id);
  await loadAttachments();
  subscribeChannel(channel.id);
}

async function loadMessages(channelId) {
  const list = $("messages");
  list.innerHTML = "";
  postState("messages.list", "loading");
  try {
    const { items } = await api.get(`/chat/real_time_messaging/${channelId}/messages?limit=100`);
    if (!items.length) {
      list.appendChild(el("li", { class: "empty" }, "No messages yet."));
      postState("messages.list", "empty");
    } else {
      for (const m of items.reverse ? items : items) {
        list.appendChild(buildMessageNode(m));
      }
      postState("messages.list", "success", { count: items.length });
    }
  } catch (err) {
    postState("messages.list", "error", { message: err.message });
    toast(err.message, "error");
  }
}

function buildMessageNode(message) {
  const editId = message.id;
  const li = el("li", { "data-id": message.id, "data-client": message.client_id || "" },
    el("div", { class: "meta" }, `#${message.id} · ${message.topic || "general"} · ${fmtDate(message.created_at)}${message.edited ? " · edited" : ""}`),
    el("div", { class: "body" }, message.body || "(deleted)"),
    el("div", { class: "actions" },
      el("button", {
        onclick: () => editMessage(editId),
      }, "Edit"),
      el("button", {
        class: "danger",
        onclick: () => deleteMessage(editId),
      }, "Delete"),
    ),
  );
  return li;
}

async function editMessage(id) {
  const newBody = prompt("Edit message text");
  if (!newBody) return;
  try {
    await api.patch(`/chat/real_time_messaging/${id}`, { body: newBody });
    await loadMessages(state.channelId);
    toast("Message updated", "success");
  } catch (err) {
    toast(err.message, "error");
  }
}

async function deleteMessage(id) {
  if (!confirm("Delete this message?")) return;
  try {
    await api.del(`/chat/real_time_messaging/${id}`);
    await loadMessages(state.channelId);
    toast("Message removed", "success");
  } catch (err) {
    toast(err.message, "error");
  }
}

async function loadMembers() {
  try {
    const { items } = await api.get(`/chat/workspaces_and_channels/${state.workspaceId}/members`);
    const ul = $("members");
    ul.innerHTML = "";
    for (const m of items) {
      ul.appendChild(el("li", {}, `${m.user_id} — role: ${m.role} (${m.status})`));
    }
  } catch (err) {
    toast(err.message, "error");
  }
}

async function loadInvitations() {
  try {
    const { items } = await api.get(`/chat/membership_lifecycle/${state.workspaceId}/invitations`);
    const ul = $("invitations");
    ul.innerHTML = "";
    for (const inv of items) {
      const li = el("li", {}, `${inv.email} → ${inv.proposed_role} (${inv.status})`);
      ul.appendChild(li);
    }
  } catch (err) {
    toast(err.message, "error");
  }
}

async function loadAudit() {
  try {
    const { items } = await api.get(`/chat/channel_management/${state.workspaceId}/audit`);
    const ul = $("audit");
    ul.innerHTML = "";
    for (const e of items.slice(0, 20)) {
      ul.appendChild(el("li", {}, `${fmtDate(e.created_at)} · ${e.action} · ${e.detail}`));
    }
  } catch (_e) { /* not allowed */ }
}

async function loadAttachments() {
  if (!state.channelId) return;
  try {
    const { items } = await api.get("/chat/attachments");
    const ul = $("attachments");
    ul.innerHTML = "";
    for (const a of items.slice(0, 8)) {
      ul.appendChild(el("li", {}, `${a.original_filename} (${a.size_bytes} bytes)`));
    }
  } catch (_e) {}
}

function connectWebSocket() {
  if (state.ws) {
    try { state.ws.close(); } catch (_e) {}
  }
  const protocol = window.location.protocol === "https:" ? "wss" : "ws";
  state.ws = new WebSocket(`${protocol}://${window.location.host}/ws/chat`);
  state.ws.addEventListener("open", () => {
    $("ws-status").textContent = "WebSocket connected.";
  });
  state.ws.addEventListener("close", () => {
    $("ws-status").textContent = "WebSocket disconnected. Reconnecting...";
    setTimeout(connectWebSocket, 2000);
  });
  state.ws.addEventListener("message", (event) => {
    let envelope;
    try { envelope = JSON.parse(event.data); } catch (e) { return; }
    handleRealtime(envelope);
  });
}

function handleRealtime(envelope) {
  if (!envelope || !envelope.type) return;
  if (envelope.type === "welcome") {
    state.connectionId = envelope.data.connection_id;
  } else if (envelope.type === "message.created" && envelope.data.message) {
    const message = envelope.data.message;
    if (message.channel_id !== state.channelId) return;
    const list = $("messages");
    const existing = list.querySelector(`[data-client="${message.client_id}"]`);
    if (existing) {
      existing.replaceWith(buildMessageNode(message));
    } else {
      list.appendChild(buildMessageNode(message));
    }
  } else if (envelope.type === "heartbeat") {
    state.eventId = envelope.data.ts || state.eventId;
  } else if (envelope.type === "error") {
    reportError(envelope.data.code || "ws_error", envelope.data.message || "WebSocket error", "websocket");
  }
}

function subscribeChannel(channelId) {
  if (!state.ws || state.ws.readyState !== 1) return;
  state.ws.send(JSON.stringify({
    type: "subscribe",
    data: { channel: `channel:${channelId}` },
  }));
}

function setupFormHandlers() {
  $("create-workspace").addEventListener("submit", async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    try {
      await api.post("/chat/workspaces_and_channels", data);
      toast("Workspace created", "success");
      await loadWorkspaces();
    } catch (err) { toast(err.message, "error"); }
  });

  $("create-channel").addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!state.workspaceId) {
      toast("Select a workspace first", "error");
      return;
    }
    const formData = new FormData(e.target);
    const payload = {
      name: formData.get("name"),
      topic: formData.get("topic"),
      is_private: formData.get("is_private") === "on",
    };
    try {
      await api.post(`/chat/workspaces_and_channels/${state.workspaceId}/channels`, payload);
      toast("Channel created", "success");
      await selectWorkspace(state.workspace);
    } catch (err) { toast(err.message, "error"); }
  });

  $("send-message").addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!state.channelId) {
      toast("Select a channel first", "error");
      return;
    }
    const formData = new FormData(e.target);
    const body = (formData.get("body") || "").toString();
    const previewMatch = body.match(/^\/preview\s+(\S+)/);
    if (previewMatch) {
      try {
        const result = await api.post("/chat/link_preview", { url: previewMatch[1] });
        $("link-preview-output").textContent = JSON.stringify(result, null, 2);
        toast("Preview fetched", "success");
      } catch (err) { toast(err.message, "error"); }
      return;
    }
    const useTopic = formData.get("topic_change") === "on";
    const topic = useTopic ? formData.get("topic") : undefined;
    const file = formData.get("file");
    let attachmentId = null;
    if (file && file.name) {
      const fd = new FormData();
      fd.append("file", file);
      try {
        const meta = await api.upload("/chat/attachments", fd);
        attachmentId = meta.id;
      } catch (err) {
        toast(err.message, "error");
        return;
      }
    }
    try {
      const payload = {
        body,
        topic,
        client_id: cryptoRandom(),
      };
      if (state.ws && state.ws.readyState === 1) {
        state.ws.send(JSON.stringify({
          type: "message",
          data: { channel_id: state.channelId, body, topic, client_id: payload.client_id },
        }));
      } else {
        await api.post(`/chat/real_time_messaging/${state.channelId}/messages`, payload);
      }
      e.target.reset();
      $("topic-input").value = topic || "general";
      toast("Message queued", "success");
    } catch (err) { toast(err.message, "error"); }
  });

  $("link-preview-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      const data = Object.fromEntries(new FormData(e.target).entries());
      const result = await api.post("/chat/link_preview", data);
      $("link-preview-output").textContent = JSON.stringify(result, null, 2);
    } catch (err) { toast(err.message, "error"); }
  });

  $("search-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target).entries());
    const params = new URLSearchParams();
    for (const [k, v] of Object.entries(data)) if (v) params.append(k, v);
    try {
      const result = await api.get(`/chat/message_history_and_search?${params.toString()}`);
      const ul = $("search-results");
      ul.innerHTML = "";
      const results = result.results || [];
      if (!results.length) ul.appendChild(el("li", { class: "muted" }, "No matches."));
      for (const r of results) {
        ul.appendChild(el("li", {}, `[#${r.id}] ${r.body}`));
      }
    } catch (err) { toast(err.message, "error"); }
  });

  $("invite-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!state.workspaceId) return;
    const data = Object.fromEntries(new FormData(e.target).entries());
    try {
      await api.post(`/chat/workspaces_and_channels/${state.workspaceId}/members`, data);
      toast("Invitation sent", "success");
      await loadInvitations();
    } catch (err) { toast(err.message, "error"); }
  });

  $("channel-settings-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!state.channelId) return;
    const data = Object.fromEntries(new FormData(e.target).entries());
    try {
      const updated = await api.patch(`/chat/channel_management/${state.channelId}`, data);
      toast("Settings saved", "success");
      await selectChannel(updated);
    } catch (err) { toast(err.message, "error"); }
  });

  $("channel-rename-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!state.channelId) return;
    const data = Object.fromEntries(new FormData(e.target).entries());
    try {
      const updated = await api.post(`/chat/channel_management/${state.channelId}/rename`, data);
      toast("Channel renamed", "success");
      await loadMessages(state.channelId);
      await selectWorkspace(state.workspace);
    } catch (err) { toast(err.message, "error"); }
  });

  $("archive-btn").addEventListener("click", async () => {
    if (!state.channelId) return;
    try {
      await api.post(`/chat/channel_management/${state.channelId}/archive`);
      toast("Channel archived", "success");
      await selectWorkspace(state.workspace);
    } catch (err) { toast(err.message, "error"); }
  });

  $("unarchive-btn").addEventListener("click", async () => {
    if (!state.channelId) return;
    try {
      await api.post(`/chat/channel_management/${state.channelId}/unarchive`);
      toast("Channel restored", "success");
      await selectWorkspace(state.workspace);
    } catch (err) { toast(err.message, "error"); }
  });

  $("open-conn").addEventListener("click", async () => {
    try {
      const result = await api.post("/chat/connection_and_message_handling/connections");
      state.connectionId = result.connection_id;
      $("conn-output").textContent = JSON.stringify(result, null, 2);
      $("close-conn").disabled = false;
    } catch (err) { toast(err.message, "error"); }
  });

  $("close-conn").addEventListener("click", async () => {
    if (!state.connectionId) return;
    try {
      const result = await api.del(`/chat/connection_and_message_handling/connections/${state.connectionId}`);
      $("conn-output").textContent = JSON.stringify(result, null, 2);
      $("close-conn").disabled = true;
      state.connectionId = null;
    } catch (err) { toast(err.message, "error"); }
  });

  $("ack-btn").addEventListener("click", async () => {
    if (!state.connectionId) return;
    try {
      const last = $("last-event-id").value || state.eventId;
      const result = await api.post(
        `/chat/connection_and_message_handling/connections/${state.connectionId}/events`,
        { last_event_id: last, state: "delivered" }
      );
      $("conn-output").textContent = JSON.stringify(result, null, 2);
    } catch (err) { toast(err.message, "error"); }
  });

  $("profile-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      const data = Object.fromEntries(new FormData(e.target).entries());
      const cleaned = Object.fromEntries(Object.entries(data).filter(([_, v]) => v));
      const result = await api.patch("/chat/accounts/me", cleaned);
      toast("Profile updated", "success");
      console.log("profile", result);
    } catch (err) { toast(err.message, "error"); }
  });
}

function cryptoRandom() {
  if (window.crypto && crypto.getRandomValues) {
    const arr = new Uint8Array(8);
    crypto.getRandomValues(arr);
    return Array.from(arr, (b) => b.toString(16).padStart(2, "0")).join("");
  }
  return Math.random().toString(16).slice(2);
}
