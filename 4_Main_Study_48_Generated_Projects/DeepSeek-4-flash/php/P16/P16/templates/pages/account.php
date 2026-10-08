<div class="page-head">
  <div>
    <h1>Account Access</h1>
    <div class="sub">Your profile and access history (SYS-01)</div>
  </div>
</div>

<div class="grid cols-2">
  <div class="card">
    <h2>Profile</h2>
    <table>
      <tbody>
        <tr><th style="width:140px">Username</th><td><?= $this->e($user['username'] ?? '') ?></td></tr>
        <tr><th>Full name</th><td><?= $this->e($user['full_name'] ?? '') ?></td></tr>
        <tr><th>Email</th><td><?= $this->e($user['email'] ?? '') ?></td></tr>
        <tr><th>Role</th><td><?= $this->badge($user['role'] ?? 'viewer') ?></td></tr>
      </tbody>
    </table>
    <p style="color:var(--muted);font-size:12px;margin-bottom:0">
      Registrations receive the <code>operator</code> role. Sessions persist in SQLite with an HTTP-only cookie.
    </p>
  </div>
  <div class="card">
    <h2>Record access event</h2>
    <form id="formAccess" class="form-grid">
      <div class="form-row">
        <label>Action</label>
        <select name="action">
          <option value="access_record">access_record</option>
          <option value="login">login</option>
          <option value="logout">logout</option>
          <option value="password_reset">password_reset</option>
          <option value="session_revoke">session_revoke</option>
        </select>
      </div>
      <div class="form-row">
        <label>Outcome</label>
        <select name="outcome">
          <option value="success">success</option>
          <option value="failure">failure</option>
        </select>
      </div>
      <div class="form-row">
        <label>Details</label>
        <textarea name="details" placeholder="Optional note"></textarea>
      </div>
      <div class="form-row" style="justify-content:end">
        <button type="submit" class="btn btn-primary">Record event</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <h2>Access history</h2>
  <div class="toolbar">
    <div class="form-row">
      <label>Action</label>
      <select id="actionFilter">
        <option value="">All</option>
        <option>register</option><option>login</option><option>logout</option><option>access_record</option>
      </select>
    </div>
    <div class="form-row">
      <label>Outcome</label>
      <select id="outcomeFilter">
        <option value="">All</option>
        <option value="success">success</option>
        <option value="failure">failure</option>
      </select>
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th><th>Time</th><th>Username</th><th>Action</th><th>Outcome</th><th>IP</th><th>Details</th>
      </tr>
    </thead>
    <tbody id="accessBody"></tbody>
  </table>
  <div id="accessEmpty" class="empty">No access records.</div>
</div>

<script>
P16.onload(function () {
  const body = document.getElementById("accessBody");
  const empty = document.getElementById("accessEmpty");
  const actionFilter = document.getElementById("actionFilter");
  const outcomeFilter = document.getElementById("outcomeFilter");

  async function load() {
    const qs = new URLSearchParams();
    if (actionFilter.value) qs.set("action", actionFilter.value);
    if (outcomeFilter.value) qs.set("outcome", outcomeFilter.value);
    const res = await P16.api("/api/sys/account_access?" + qs.toString());
    body.innerHTML = "";
    empty.style.display = res.data.length ? "none" : "block";
    res.data.forEach(function (a) {
      const tr = document.createElement("tr");
      tr.innerHTML =
        "<td>" + a.id + "</td>" +
        "<td class='mono'>" + P16.esc(a.created_at) + "</td>" +
        "<td>" + P16.esc(a.username || "—") + "</td>" +
        "<td>" + P16.badge(a.action) + "</td>" +
        "<td>" + P16.badge(a.outcome) + "</td>" +
        "<td class='mono'>" + P16.esc(a.ip_address || "—") + "</td>" +
        "<td>" + P16.esc(a.details) + "</td>";
      body.appendChild(tr);
    });
  }

  actionFilter.addEventListener("change", load);
  outcomeFilter.addEventListener("change", load);

  document.getElementById("formAccess").addEventListener("submit", function (e) {
    e.preventDefault();
    P16.submitForm(this, {
      onSuccess: function () { this.reset(); load(); }.bind(this),
    });
  });

  load();
});
</script>
