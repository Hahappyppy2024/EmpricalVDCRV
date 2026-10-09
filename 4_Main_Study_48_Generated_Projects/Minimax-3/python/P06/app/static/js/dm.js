import { api, toast } from "./app.js";

document.addEventListener("DOMContentLoaded", init);

const state = {
  threads: [],
  current: null,
};

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
  await loadThreads();
  document.getElementById("dm-compose").addEventListener("submit", sendMessage);
}

async function loadThreads() {
  const { items } = await api.get("/chat/direct_messages");
  state.threads = items;
  const ul = document.getElementById("dm-threads");
  ul.innerHTML = "";
  if (!items.length) {
    ul.appendChild(el("li", { class: "empty muted" }, "No conversations yet."));
  }
  for (const t of items) {
    const li = el("li", {
      onclick: () => openThread(t.other_user.id),
    }, `${t.other_user.display_name} (${t.unread_count} unread)`,
       el("span", { class: "meta" }, t.last_body));
    ul.appendChild(li);
  }
  if (items.length) {
    openThread(items[0].other_user.id);
  }
}

async function openThread(otherUserId) {
  try {
    const thread = await api.get(`/chat/direct_messages/${otherUserId}`);
    state.current = thread;
    document.getElementById("dm-title").textContent = `Chat with ${thread.other_user.display_name}`;
    const list = document.getElementById("dm-messages");
    list.innerHTML = "";
    if (!thread.messages.length) {
      list.appendChild(el("li", { class: "empty muted" }, "Say hello!"));
    }
    for (const m of thread.messages) {
      list.appendChild(el("li", { class: m.outgoing ? "outgoing" : "incoming" },
        el("div", { class: "meta" }, `${m.outgoing ? "you" : thread.other_user.display_name} · ${new Date(m.created_at).toLocaleString()}`),
        el("div", { class: "body" }, m.body),
      ));
    }
  } catch (err) { toast(err.message, "error"); }
}

async function sendMessage(event) {
  event.preventDefault();
  if (!state.current) {
    toast("Open a conversation first", "error");
    return;
  }
  const formData = new FormData(event.target);
  const body = formData.get("body");
  try {
    await api.post(`/chat/direct_messages/${state.current.other_user.id}`, { body });
    event.target.reset();
    await openThread(state.current.other_user.id);
    await loadThreads();
  } catch (err) { toast(err.message, "error"); }
}
