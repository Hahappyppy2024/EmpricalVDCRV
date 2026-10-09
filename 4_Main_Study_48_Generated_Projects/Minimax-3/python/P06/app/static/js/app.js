// Common helpers used by every page.

export const api = {
  get(path) {
    return fetch(`/api${path}`, { credentials: "same-origin" }).then(handleResponse);
  },
  post(path, body) {
    return fetch(`/api${path}`, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: body ? JSON.stringify(body) : null,
    }).then(handleResponse);
  },
  patch(path, body) {
    return fetch(`/api${path}`, {
      method: "PATCH",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body || {}),
    }).then(handleResponse);
  },
  del(path) {
    return fetch(`/api${path}`, {
      method: "DELETE",
      credentials: "same-origin",
    }).then(handleResponse);
  },
  upload(path, formData) {
    return fetch(`/api${path}`, {
      method: "POST",
      credentials: "same-origin",
      body: formData,
    }).then(handleResponse);
  },
};

async function handleResponse(res) {
  const ctype = res.headers.get("Content-Type") || "";
  let data;
  if (ctype.includes("application/json")) {
    data = await res.json();
  } else {
    data = await res.text();
  }
  if (!res.ok) {
    const message = (data && data.message) || res.statusText;
    throw new ChatError(data && data.code, message, res.status);
  }
  return data;
}

export class ChatError extends Error {
  constructor(code, message, status) {
    super(message);
    this.code = code;
    this.status = status;
  }
}

export function toast(message, level = "info") {
  const existing = document.querySelector(".toast");
  if (existing) existing.remove();
  const el = document.createElement("div");
  el.className = `toast ${level}`;
  el.textContent = message;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

export function setText(el, text) {
  if (el) el.textContent = text;
}

document.addEventListener("click", (ev) => {
  if (ev.target && ev.target.id === "signout-btn") {
    api.post("/chat/accounts/signout")
      .then(() => {
        toast("Signed out", "success");
        setTimeout(() => (window.location.href = "/"), 600);
      })
      .catch((err) => toast(err.message, "error"));
  }
});

export function reportError(code, message, source = "client") {
  try {
    return api.post("/chat/errors", { code, message, source }).catch(() => null);
  } catch (_e) {
    return Promise.resolve(null);
  }
}

window.addEventListener("error", (event) => {
  reportError("uncaught_error", event.message || "Uncaught error", "window.onerror");
});
window.addEventListener("unhandledrejection", (event) => {
  const reason = event.reason && event.reason.message ? event.reason.message : String(event.reason);
  reportError("unhandled_rejection", reason, "promise");
});

export function postState(context, status, payload = {}) {
  return api.post("/chat/frontend_api_integration", { context, status, payload }).catch(() => null);
}
