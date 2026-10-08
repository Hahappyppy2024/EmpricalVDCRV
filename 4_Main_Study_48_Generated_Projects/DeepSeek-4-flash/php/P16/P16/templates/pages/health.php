<div class="page-head">
  <div>
    <h1>Health Check Targets</h1>
    <div class="sub">Configure HTTP/TCP health checks for internal services (SYS-10)</div>
  </div>
</div>

<div class="card">
  <h2>Add target</h2>
  <form id="formTarget" class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
    <div class="form-row">
      <label>Name</label>
      <input type="text" name="name" placeholder="web-ui" required>
    </div>
    <div class="form-row">
      <label>Protocol</label>
      <select name="protocol">
        <option value="http">http</option>
        <option value="tcp">tcp</option>
      </select>
    </div>
    <div class="form-row">
      <label>Target URL / host</label>
      <input type="text" name="target" placeholder="http://127.0.0.1:8787/ or 127.0.0.1" required>
    </div>
    <div class="form-row">
      <label>Port</label>
      <input type="number" name="port" min="1" max="65535" placeholder="80">
    </div>
    <div class="form-row">
      <label>Interval (s)</label>
      <input type="number" name="interval_seconds" min="5" max="86400" value="60">
    </div>
    <div class="form-row" style="justify-content:end">
      <button type="submit" class="btn btn-primary">Add target</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>Targets</h2>
  <table>
    <thead>
      <tr>
        <th>Name</th><th>Protocol</th><th>Target</th><th>Status</th><th>Last code</th>
        <th>Latency</th><th>Last checked</th><th style="width:130px">Actions</th>
      </tr>
    </thead>
    <tbody id="targetBody"></tbody>
  </table>
  <div id="targetEmpty" class="empty">No health targets configured.</div>
</div>

<script>
P16.onload(function () {
  const body = document.getElementById("targetBody");
  const empty = document.getElementById("targetEmpty");

  async function load() {
    const res = await P16.api("/api/sys/health_check_targets");
    body.innerHTML = "";
    empty.style.display = res.data.length ? "none" : "block";
    res.data.forEach(function (t) {
      const tr = document.createElement("tr");
      const dot = '<span class="status-dot ' + P16.esc(t.status) + '"></span>';
      tr.innerHTML =
        "<td><strong>" + P16.esc(t.name) + "</strong></td>" +
        "<td>" + P16.esc(t.protocol) + "</td>" +
        "<td class='mono'>" + P16.esc(t.target) + (t.port ? ":" + t.port : "") + "</td>" +
        "<td>" + dot + P16.badge(t.status) + "</td>" +
        "<td>" + (t.last_code === null ? "—" : t.last_code) + "</td>" +
        "<td>" + (t.last_latency_ms === null ? "—" : t.last_latency_ms + "ms") + "</td>" +
        "<td class='mono'>" + P16.esc(t.last_checked_at || "—") + "</td>" +
        "<td>" +
        '<button class="btn btn-sm btn-success" data-check="' + t.id + '">Check</button> ' +
        '<button class="btn btn-sm btn-danger" data-del="' + t.id + '">Delete</button>' +
        "</td>";
      body.appendChild(tr);
    });
  }

  body.addEventListener("click", function (ev) {
    const check = ev.target.closest("button[data-check]");
    const del = ev.target.closest("button[data-del]");
    if (check) {
      P16.api("/api/sys/health_check_targets/" + check.dataset.check + "/check", { method: "POST" })
        .then(function (b) { P16.toast("Target " + b.data.name + " is " + b.data.status + ".", b.data.status === "up" ? "ok" : "err"); load(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    } else if (del) {
      if (!confirm("Delete this target?")) return;
      P16.api("/api/sys/health_check_targets/" + del.dataset.id, { method: "DELETE" })
        .then(function () { P16.toast("Target deleted.", "ok"); load(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    }
  });

  document.getElementById("formTarget").addEventListener("submit", function (e) {
    e.preventDefault();
    P16.submitForm(this, {
      onSuccess: function () { this.reset(); load(); }.bind(this),
    });
  });

  load();
});
</script>
