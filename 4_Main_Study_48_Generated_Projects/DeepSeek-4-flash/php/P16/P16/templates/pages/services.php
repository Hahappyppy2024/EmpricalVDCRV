<div class="page-head">
  <div>
    <h1>Service Control</h1>
    <div class="sub">Start, stop, restart and inspect mock services (SYS-04)</div>
  </div>
</div>

<div class="card">
  <h2>Mock services</h2>
  <table>
    <thead>
      <tr>
        <th>Name</th><th>Status</th><th>Uptime</th><th>Description</th>
        <th>Last action</th><th style="width:220px">Actions</th>
      </tr>
    </thead>
    <tbody id="svcBody"></tbody>
  </table>
  <div id="svcEmpty" class="empty">No services registered.</div>
</div>

<div class="card">
  <h2>Register service</h2>
  <form id="formService" class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
    <div class="form-row">
      <label>Name</label>
      <input type="text" name="name" placeholder="cache-cluster" required>
    </div>
    <div class="form-row">
      <label>Description</label>
      <input type="text" name="description" placeholder="Optional description">
    </div>
    <div class="form-row" style="justify-content:end">
      <button type="submit" class="btn btn-primary">Register service</button>
    </div>
  </form>
</div>

<script>
P16.onload(function () {
  const body = document.getElementById("svcBody");
  const empty = document.getElementById("svcEmpty");

  async function load() {
    const res = await P16.api("/api/sys/service_control");
    body.innerHTML = "";
    empty.style.display = res.data.length ? "none" : "block";
    res.data.forEach(function (s) {
      const tr = document.createElement("tr");
      tr.innerHTML =
        "<td><strong>" + P16.esc(s.name) + "</strong></td>" +
        "<td>" + P16.badge(s.status) + "</td>" +
        "<td>" + P16.fmtUptime(s.uptime_seconds) + "</td>" +
        "<td>" + P16.esc(s.description) + "</td>" +
        "<td class='mono'>" + P16.esc(s.last_action || "—") + (s.last_action_at ? "<br>" + P16.esc(s.last_action_at) : "") + "</td>" +
        "<td>" +
        actionBtn(s, "start", "Start") +
        actionBtn(s, "stop", "Stop") +
        actionBtn(s, "restart", "Restart") +
        actionBtn(s, "inspect", "Inspect") +
        '<button class="btn btn-sm btn-danger" data-del="' + s.id + '">Delete</button>' +
        "</td>";
      body.appendChild(tr);
    });
  }

  function actionBtn(s, action, label) {
    const disabled = (action === "start" && !["stopped", "down", "unknown"].includes(s.status)) ||
      (action === "stop" && !["running", "starting", "restarting", "stopping"].includes(s.status)) ||
      (action === "restart" && !["running", "starting", "restarting", "stopping"].includes(s.status));
    return '<button class="btn btn-sm" data-action="' + action + '" data-id="' + s.id + '"' +
      (disabled ? " disabled" : "") + ">" + label + "</button> ";
  }

  body.addEventListener("click", function (ev) {
    const act = ev.target.closest("button[data-action]");
    const del = ev.target.closest("button[data-del]");
    if (act) {
      P16.api("/api/sys/service_control/" + act.dataset.id, { method: "PATCH", body: { action: act.dataset.action } })
        .then(function (b) { P16.toast("Service " + b.data.name + " → " + b.data.status, "ok"); load(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    } else if (del) {
      if (!confirm("Delete this service?")) return;
      P16.api("/api/sys/service_control/" + del.dataset.id, { method: "DELETE" })
        .then(function () { P16.toast("Service deleted.", "ok"); load(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    }
  });

  document.getElementById("formService").addEventListener("submit", function (e) {
    e.preventDefault();
    P16.submitForm(this, {
      onSuccess: function () { this.reset(); load(); }.bind(this),
    });
  });

  load();
});
</script>
