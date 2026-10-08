<div class="page-head">
  <div>
    <h1>Alert Center</h1>
    <div class="sub">View, acknowledge, assign and comment on alerts (SYS-09)</div>
  </div>
</div>

<div class="card">
  <h2>Open alert</h2>
  <form id="formAlert" class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
    <div class="form-row">
      <label>Title</label>
      <input type="text" name="title" placeholder="High latency on api-gateway" required>
    </div>
    <div class="form-row">
      <label>Severity</label>
      <select name="severity">
        <option value="warning">warning</option>
        <option value="critical">critical</option>
        <option value="info">info</option>
      </select>
    </div>
    <div class="form-row">
      <label>Source</label>
      <input type="text" name="source" value="manual" required>
    </div>
    <div class="form-row" style="justify-content:end">
      <button type="submit" class="btn btn-primary">Open alert</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>Alerts</h2>
  <div class="toolbar">
    <div class="form-row">
      <label>Status</label>
      <select id="statusFilter">
        <option value="all">All</option>
        <option value="open">open</option>
        <option value="acknowledged">acknowledged</option>
        <option value="assigned">assigned</option>
        <option value="closed">closed</option>
      </select>
    </div>
    <div class="form-row">
      <label>Severity</label>
      <select id="severityFilter">
        <option value="">All</option>
        <option value="critical">critical</option>
        <option value="warning">warning</option>
        <option value="info">info</option>
      </select>
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th>Title</th><th>Severity</th><th>Status</th><th>Source</th><th>Assignee</th><th>Created</th><th style="width:220px">Actions</th>
      </tr>
    </thead>
    <tbody id="alertBody"></tbody>
  </table>
  <div id="alertEmpty" class="empty">No alerts match the filter.</div>
</div>

<div id="comments"></div>

<script>
P16.onload(function () {
  const body = document.getElementById("alertBody");
  const empty = document.getElementById("alertEmpty");
  const commentsBox = document.getElementById("comments");
  const statusFilter = document.getElementById("statusFilter");
  const severityFilter = document.getElementById("severityFilter");

  async function load() {
    const qs = new URLSearchParams();
    if (statusFilter.value !== "all") qs.set("status", statusFilter.value);
    if (severityFilter.value) qs.set("severity", severityFilter.value);
    const res = await P16.api("/api/sys/alert_center?" + qs.toString());
    body.innerHTML = "";
    empty.style.display = res.data.length ? "none" : "block";
    res.data.forEach(function (a) {
      const tr = document.createElement("tr");
      tr.innerHTML =
        "<td><strong>" + P16.esc(a.title) + "</strong></td>" +
        "<td>" + P16.badge(a.severity) + "</td>" +
        "<td>" + P16.badge(a.status) + "</td>" +
        "<td>" + P16.esc(a.source) + "</td>" +
        "<td>" + P16.esc(a.assignee_name || "—") + "</td>" +
        "<td class='mono'>" + P16.esc(a.created_at) + "</td>" +
        "<td>" +
        '<button class="btn btn-sm" data-act="acknowledge" data-id="' + a.id + '"' + (a.status === "closed" ? " disabled" : "") + ">Ack</button> " +
        '<button class="btn btn-sm" data-act="assign" data-id="' + a.id + '"' + (a.status === "closed" ? " disabled" : "") + ">Assign</button> " +
        '<button class="btn btn-sm" data-act="comment" data-id="' + a.id + '"' + (a.status === "closed" ? " disabled" : "") + ">Comment</button> " +
        '<button class="btn btn-sm btn-success" data-act="close" data-id="' + a.id + '"' + (a.status === "closed" ? " disabled" : "") + ">Close</button> " +
        '<button class="btn btn-sm btn-danger" data-del="' + a.id + '">Del</button>' +
        "</td>";
      body.appendChild(tr);
    });
  }

  async function loadComments() {
    const res = await P16.api("/api/sys/alert_center");
    const withComments = res.data.filter(function (a) { return a.comments; });
    if (!withComments.length) { commentsBox.innerHTML = ""; return; }
    commentsBox.innerHTML = '<div class="card"><h2>Comment history</h2>' +
      withComments.map(function (a) {
        return '<div style="margin-bottom:10px"><strong>' + P16.esc(a.title) + "</strong>" +
          "<div class='mono' style='white-space:pre-wrap'>" + P16.esc(a.comments) + "</div></div>";
      }).join("") + "</div>";
  }

  body.addEventListener("click", function (ev) {
    const act = ev.target.closest("button[data-act]");
    const del = ev.target.closest("button[data-del]");
    if (act) {
      const payload = { action: act.dataset.act };
      if (act.dataset.act === "comment") {
        const comment = prompt("Add comment:");
        if (comment === null) return;
        payload.comment = comment;
      }
      P16.api("/api/sys/alert_center/" + act.dataset.id, { method: "PATCH", body: payload })
        .then(function (b) { P16.toast("Alert " + b.data.status + ".", "ok"); load(); loadComments(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    } else if (del) {
      if (!confirm("Delete this alert?")) return;
      P16.api("/api/sys/alert_center/" + del.dataset.id, { method: "DELETE" })
        .then(function () { P16.toast("Alert deleted.", "ok"); load(); loadComments(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    }
  });

  statusFilter.addEventListener("change", load);
  severityFilter.addEventListener("change", load);

  document.getElementById("formAlert").addEventListener("submit", function (e) {
    e.preventDefault();
    P16.submitForm(this, {
      onSuccess: function () { this.reset(); load(); }.bind(this),
    });
  });

  load();
  loadComments();
});
</script>
