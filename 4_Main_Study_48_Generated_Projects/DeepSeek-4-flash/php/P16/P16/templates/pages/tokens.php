<div class="page-head">
  <div>
    <h1>API Token Manager</h1>
    <div class="sub">Create, revoke and audit monitoring API tokens (SYS-11 · admin)</div>
  </div>
</div>

<div class="card">
  <h2>Create token</h2>
  <form id="formToken" class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
    <div class="form-row">
      <label>Name</label>
      <input type="text" name="name" placeholder="grafana-reader" required>
    </div>
    <div class="form-row">
      <label>Owner</label>
      <select name="owner_id" id="ownerSelect"></select>
    </div>
    <div class="form-row" style="justify-content:end">
      <button type="submit" class="btn btn-primary">Create token</button>
    </div>
  </form>
  <div id="tokenResult" style="display:none;margin-top:12px">
    <div class="token-box" id="tokenBox"></div>
    <div class="metrics-note">Copy this token now — it is shown only once. Use it as a Bearer token at <code>/api/monitor/metrics</code>.</div>
  </div>
</div>

<div class="card">
  <h2>Tokens</h2>
  <div class="toolbar">
    <div class="form-row">
      <label>Status</label>
      <select id="statusFilter">
        <option value="">All</option>
        <option value="active">active</option>
        <option value="revoked">revoked</option>
      </select>
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th>Name</th><th>Prefix</th><th>Owner</th><th>Status</th><th>Created</th>
        <th>Last used</th><th style="width:140px">Actions</th>
      </tr>
    </thead>
    <tbody id="tokenBody"></tbody>
  </table>
  <div id="tokenEmpty" class="empty">No tokens yet.</div>
</div>

<script>
P16.onload(function () {
  const body = document.getElementById("tokenBody");
  const empty = document.getElementById("tokenEmpty");
  const statusFilter = document.getElementById("statusFilter");

  async function load() {
    const qs = new URLSearchParams();
    if (statusFilter.value) qs.set("status", statusFilter.value);
    const res = await P16.api("/api/sys/api_token_manager?" + qs.toString());
    body.innerHTML = "";
    empty.style.display = res.data.length ? "none" : "block";
    res.data.forEach(function (t) {
      const tr = document.createElement("tr");
      tr.innerHTML =
        "<td><strong>" + P16.esc(t.name) + "</strong></td>" +
        "<td class='mono'>" + P16.esc(t.token_prefix || "—") + "</td>" +
        "<td>" + P16.esc(t.owner_name) + "</td>" +
        "<td>" + P16.badge(t.status) + "</td>" +
        "<td class='mono'>" + P16.esc(t.created_at) + "</td>" +
        "<td class='mono'>" + P16.esc(t.last_used_at || "—") + "</td>" +
        "<td>" +
        '<button class="btn btn-sm btn-danger" data-revoke="' + t.id + '"' + (t.status === "revoked" ? " disabled" : "") + ">Revoke</button> " +
        '<button class="btn btn-sm" data-del="' + t.id + '">Delete</button>' +
        "</td>";
      body.appendChild(tr);
    });
  }

  async function loadOwners() {
    const res = await P16.api("/api/sys/audit_logs_and_admin_operations/users");
    document.getElementById("ownerSelect").innerHTML =
      res.data.map(function (u) {
        return '<option value="' + u.id + '">' + P16.esc(u.username) + " (" + P16.esc(u.role) + ")</option>";
      }).join("");
  }

  body.addEventListener("click", function (ev) {
    const revoke = ev.target.closest("button[data-revoke]");
    const del = ev.target.closest("button[data-del]");
    if (revoke) {
      if (!confirm("Revoke this token? It will stop working immediately.")) return;
      P16.api("/api/sys/api_token_manager/" + revoke.dataset.revoke, { method: "PATCH", body: { action: "revoke" } })
        .then(function () { P16.toast("Token revoked.", "ok"); load(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    } else if (del) {
      if (!confirm("Delete this token permanently?")) return;
      P16.api("/api/sys/api_token_manager/" + del.dataset.id, { method: "DELETE" })
        .then(function () { P16.toast("Token deleted.", "ok"); load(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    }
  });

  statusFilter.addEventListener("change", load);

  document.getElementById("formToken").addEventListener("submit", function (e) {
    e.preventDefault();
    P16.submitForm(this, {
      onSuccess: function (b) {
        this.reset();
        document.getElementById("tokenResult").style.display = "block";
        document.getElementById("tokenBox").textContent = b.plain_token;
        P16.toast("Token created. Copy it now.", "ok");
        load();
      },
    });
  });

  load();
  loadOwners();
});
</script>
