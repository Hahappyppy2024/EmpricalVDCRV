<div class="page-head">
  <div>
    <h1>Audit Logs &amp; Admin Operations</h1>
    <div class="sub">Review privileged operations and manage operators (SYS-12 · admin)</div>
  </div>
</div>

<div class="card">
  <h2>Audit events</h2>
  <div class="toolbar">
    <div class="form-row">
      <label>Module</label>
      <select id="moduleFilter">
        <option value="">All modules</option>
        <option>configuration_editor</option><option>service_control</option><option>job_scheduler</option>
        <option>job_execution_history</option><option>backup_manager</option><option>alert_center</option>
        <option>health_check_targets</option><option>api_token_manager</option><option>account_access</option>
        <option>audit_logs_and_admin_operations</option>
      </select>
    </div>
    <div class="form-row">
      <label>Action</label>
      <select id="actionFilter">
        <option value="">All actions</option>
        <option>login</option><option>logout</option><option>config_change</option><option>config_approve</option>
        <option>service_action</option><option>job_run</option><option>job_create</option><option>backup_create</option>
        <option>token_create</option><option>token_revoke</option><option>operator_create</option>
        <option>operator_toggle</option><option>operator_role</option>
      </select>
    </div>
    <div class="form-row">
      <label>Username</label>
      <input type="text" id="usernameInput" placeholder="operator">
    </div>
    <div class="form-row">
      <label>From</label>
      <input type="date" id="fromInput">
    </div>
    <div class="form-row">
      <label>To</label>
      <input type="date" id="toInput">
    </div>
    <div class="form-row">
      <button class="btn btn-primary" id="btnSearch" type="button">Search</button>
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Detail</th>
      </tr>
    </thead>
    <tbody id="auditBody"></tbody>
  </table>
  <div id="auditEmpty" class="empty">No audit events match the filter.</div>
</div>

<div class="card">
  <h2>Manage operators</h2>
  <form id="formOperator" class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
    <div class="form-row">
      <label>Full name</label>
      <input type="text" name="full_name" placeholder="New operator">
    </div>
    <div class="form-row">
      <label>Username</label>
      <input type="text" name="username" placeholder="newoperator" required>
    </div>
    <div class="form-row">
      <label>Email</label>
      <input type="email" name="email" placeholder="new@monitor.local" required>
    </div>
    <div class="form-row">
      <label>Password</label>
      <input type="text" name="password" placeholder="min 8 chars" required>
    </div>
    <div class="form-row">
      <label>Role</label>
      <select name="role">
        <option value="operator">operator</option>
        <option value="viewer">viewer</option>
      </select>
    </div>
    <div class="form-row" style="justify-content:end">
      <button type="submit" class="btn btn-primary">Create operator</button>
    </div>
  </form>
  <table style="margin-top:14px">
    <thead>
      <tr>
        <th>Username</th><th>Full name</th><th>Role</th><th>Email</th><th>Active</th><th style="width:210px">Actions</th>
      </tr>
    </thead>
    <tbody id="opBody"></tbody>
  </table>
</div>

<script>
P16.onload(function () {
  const auditBody = document.getElementById("auditBody");
  const auditEmpty = document.getElementById("auditEmpty");
  const opBody = document.getElementById("opBody");

  async function loadAudit() {
    const qs = new URLSearchParams();
    if (document.getElementById("moduleFilter").value) qs.set("module", document.getElementById("moduleFilter").value);
    if (document.getElementById("actionFilter").value) qs.set("action", document.getElementById("actionFilter").value);
    if (document.getElementById("usernameInput").value.trim()) qs.set("username", document.getElementById("usernameInput").value.trim());
    if (document.getElementById("fromInput").value) qs.set("from", document.getElementById("fromInput").value + " 00:00:00");
    if (document.getElementById("toInput").value) qs.set("to", document.getElementById("toInput").value + " 23:59:59");
    const res = await P16.api("/api/sys/audit_logs_and_admin_operations?" + qs.toString());
    auditBody.innerHTML = "";
    auditEmpty.style.display = res.data.length ? "none" : "block";
    res.data.forEach(function (a) {
      const tr = document.createElement("tr");
      tr.innerHTML =
        "<td>" + a.id + "</td>" +
        "<td class='mono'>" + P16.esc(a.created_at) + "</td>" +
        "<td>" + P16.esc(a.username || "—") + "</td>" +
        "<td><span class='pill pill-info'>" + P16.esc(a.action) + "</span></td>" +
        "<td class='mono'>" + P16.esc(a.module || "—") + "</td>" +
        "<td>" + P16.esc(a.detail) + "</td>";
      auditBody.appendChild(tr);
    });
  }

  async function loadOperators() {
    const res = await P16.api("/api/sys/audit_logs_and_admin_operations/operators");
    opBody.innerHTML = "";
    res.data.forEach(function (u) {
      if (u.role === "admin") return;
      const tr = document.createElement("tr");
      tr.innerHTML =
        "<td><strong>" + P16.esc(u.username) + "</strong></td>" +
        "<td>" + P16.esc(u.full_name) + "</td>" +
        "<td>" + P16.badge(u.role) + "</td>" +
        "<td>" + P16.esc(u.email) + "</td>" +
        "<td>" + (u.active === 1 ? P16.badge("active") : P16.badge("disabled")) + "</td>" +
        "<td>" +
        '<button class="btn btn-sm" data-role="' + u.id + '">Role</button> ' +
        '<button class="btn btn-sm ' + (u.active === 1 ? "btn-danger" : "btn-success") + '" data-toggle="' + u.id + '" data-active="' + (u.active === 1 ? 0 : 1) + '">' +
        (u.active === 1 ? "Disable" : "Enable") + "</button>" +
        "</td>";
      opBody.appendChild(tr);
    });
  }

  opBody.addEventListener("click", function (ev) {
    const role = ev.target.closest("button[data-role]");
    const toggle = ev.target.closest("button[data-toggle]");
    if (role) {
      const r = prompt("New role (operator or viewer):");
      if (!r || !["operator", "viewer"].includes(r)) return;
      P16.api("/api/sys/audit_logs_and_admin_operations", { method: "POST", body: { action: "operator_role", operator_id: role.dataset.role, role: r } })
        .then(function () { P16.toast("Role updated.", "ok"); loadOperators(); loadAudit(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    } else if (toggle) {
      P16.api("/api/sys/audit_logs_and_admin_operations", { method: "POST", body: { action: "operator_toggle", operator_id: toggle.dataset.toggle, active: toggle.dataset.active } })
        .then(function () { P16.toast("Operator state updated.", "ok"); loadOperators(); loadAudit(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    }
  });

  ["moduleFilter", "actionFilter"].forEach(function (id) {
    document.getElementById(id).addEventListener("change", loadAudit);
  });
  document.getElementById("btnSearch").addEventListener("click", loadAudit);
  ["usernameInput", "fromInput", "toInput"].forEach(function (id) {
    document.getElementById(id).addEventListener("keydown", function (e) { if (e.key === "Enter") loadAudit(); });
  });

  document.getElementById("formOperator").addEventListener("submit", function (e) {
    e.preventDefault();
    P16.submitForm(this, {
      onSuccess: function (b) {
        this.reset();
        P16.toast(b.data.detail || "Operator created.", "ok");
        loadOperators();
        loadAudit();
      },
    });
  });

  loadAudit();
  loadOperators();
});
</script>
