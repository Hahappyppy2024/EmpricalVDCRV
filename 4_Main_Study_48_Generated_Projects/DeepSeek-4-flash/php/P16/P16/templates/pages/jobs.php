<div class="page-head">
  <div>
    <h1>Job Scheduler</h1>
    <div class="sub">Create, pause, run and delete background jobs from approved profiles (SYS-05)</div>
  </div>
</div>

<div class="card">
  <h2>Create job</h2>
  <form id="formJob" class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
    <div class="form-row">
      <label>Name</label>
      <input type="text" name="name" placeholder="nightly-db-backup" required>
    </div>
    <div class="form-row">
      <label>Approved profile</label>
      <select name="profile">
        <option value="nightly_backup">nightly_backup</option>
        <option value="log_rotate">log_rotate</option>
        <option value="health_check">health_check</option>
        <option value="metric_aggregation">metric_aggregation</option>
        <option value="report_generation">report_generation</option>
      </select>
    </div>
    <div class="form-row">
      <label>Schedule</label>
      <select name="schedule">
        <option value="manual">manual</option>
        <option value="hourly">hourly</option>
        <option value="daily">daily</option>
        <option value="weekly">weekly</option>
        <option value="monthly">monthly</option>
      </select>
    </div>
    <div class="form-row">
      <label>Description</label>
      <input type="text" name="description" placeholder="Optional">
    </div>
    <div class="form-row" style="justify-content:end">
      <button type="submit" class="btn btn-primary">Create job</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>Jobs</h2>
  <div class="toolbar">
    <div class="form-row">
      <label>Status</label>
      <select id="statusFilter">
        <option value="all">All</option>
        <option value="active">active</option>
        <option value="paused">paused</option>
      </select>
    </div>
    <div class="form-row">
      <label>Profile</label>
      <select id="profileFilter">
        <option value="">All profiles</option>
        <option>nightly_backup</option><option>log_rotate</option><option>health_check</option>
        <option>metric_aggregation</option><option>report_generation</option>
      </select>
    </div>
  </div>
  <table>
    <thead>
      <tr><th>Name</th><th>Profile</th><th>Schedule</th><th>Status</th><th>Owner</th><th>Description</th><th style="width:220px">Actions</th></tr>
    </thead>
    <tbody id="jobBody"></tbody>
  </table>
  <div id="jobEmpty" class="empty">No jobs yet.</div>
</div>

<script>
P16.onload(function () {
  const body = document.getElementById("jobBody");
  const empty = document.getElementById("jobEmpty");
  const statusFilter = document.getElementById("statusFilter");
  const profileFilter = document.getElementById("profileFilter");

  async function load() {
    const qs = new URLSearchParams();
    if (statusFilter.value !== "all") qs.set("status", statusFilter.value);
    if (profileFilter.value) qs.set("profile", profileFilter.value);
    const res = await P16.api("/api/sys/job_scheduler?" + qs.toString());
    body.innerHTML = "";
    empty.style.display = res.data.length ? "none" : "block";
    res.data.forEach(function (j) {
      const tr = document.createElement("tr");
      const paused = j.status === "paused";
      tr.innerHTML =
        "<td><strong>" + P16.esc(j.name) + "</strong></td>" +
        "<td class='mono'>" + P16.esc(j.profile) + "</td>" +
        "<td>" + P16.esc(j.schedule) + "</td>" +
        "<td>" + P16.badge(j.status) + "</td>" +
        "<td>" + P16.esc(j.owner_name) + "</td>" +
        "<td>" + P16.esc(j.description) + "</td>" +
        "<td>" +
        '<button class="btn btn-sm" data-act="' + (paused ? "resume" : "pause") + '" data-id="' + j.id + '">' +
        (paused ? "Resume" : "Pause") + "</button> " +
        '<button class="btn btn-sm btn-success" data-act="run" data-id="' + j.id + '">Run</button> ' +
        '<button class="btn btn-sm btn-danger" data-del="' + j.id + '">Delete</button>' +
        "</td>";
      body.appendChild(tr);
    });
  }

  body.addEventListener("click", function (ev) {
    const act = ev.target.closest("button[data-act]");
    const del = ev.target.closest("button[data-del]");
    if (act) {
      P16.api("/api/sys/job_scheduler/" + act.dataset.id, { method: "PATCH", body: { action: act.dataset.act } })
        .then(function (b) { P16.toast("Job " + b.data.name + " → " + b.data.status, "ok"); load(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    } else if (del) {
      if (!confirm("Delete this job? Its execution history is kept.")) return;
      P16.api("/api/sys/job_scheduler/" + del.dataset.id, { method: "DELETE" })
        .then(function () { P16.toast("Job deleted.", "ok"); load(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    }
  });

  statusFilter.addEventListener("change", load);
  profileFilter.addEventListener("change", load);

  document.getElementById("formJob").addEventListener("submit", function (e) {
    e.preventDefault();
    P16.submitForm(this, {
      onSuccess: function (b) {
        this.reset();
        P16.toast("Job #" + b.data.id + " created.", "ok");
        load();
      }.bind(this),
    });
  });

  load();
});
</script>
