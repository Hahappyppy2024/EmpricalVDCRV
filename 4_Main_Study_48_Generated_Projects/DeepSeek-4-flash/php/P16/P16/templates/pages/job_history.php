<div class="page-head">
  <div>
    <h1>Job Execution History</h1>
    <div class="sub">Inspect job outputs, exit status, duration and retry history (SYS-06)</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="form-row">
      <label>Exit status</label>
      <select id="exitFilter">
        <option value="">All</option>
        <option value="0">Success (0)</option>
        <option value="1">Failure (1)</option>
      </select>
    </div>
    <div class="form-row flex">
      <label>Search output</label>
      <input type="text" id="qInput" placeholder="Filter by job output…">
    </div>
    <div class="form-row">
      <button class="btn btn-primary" id="btnSearch" type="button">Search</button>
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th><th>Job</th><th>Run</th><th>Exit</th><th>Retries</th><th>Duration</th><th>Started</th><th>Output</th>
      </tr>
    </thead>
    <tbody id="runBody"></tbody>
  </table>
  <div id="runEmpty" class="empty">No execution records yet. Run a job from the scheduler.</div>
</div>

<div class="card">
  <h2>Record manual run</h2>
  <form id="formRun" class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
    <div class="form-row">
      <label>Job ID</label>
      <input type="number" name="job_id" min="1" placeholder="1" required>
    </div>
    <div class="form-row">
      <label>Exit status</label>
      <input type="number" name="exit_status" min="0" max="255" value="0" required>
    </div>
    <div class="form-row">
      <label>Duration (ms)</label>
      <input type="number" name="duration_ms" min="0" value="500">
    </div>
    <div class="form-row">
      <label>Retries</label>
      <input type="number" name="retry_count" min="0" value="0">
    </div>
    <div class="form-row" style="grid-column:1/-1">
      <label>Output</label>
      <textarea name="output" placeholder="Command output…"></textarea>
    </div>
    <div class="form-row" style="justify-content:end">
      <button type="submit" class="btn btn-primary">Record run</button>
    </div>
  </form>
</div>

<script>
P16.onload(function () {
  const body = document.getElementById("runBody");
  const empty = document.getElementById("runEmpty");
  const exitFilter = document.getElementById("exitFilter");
  const qInput = document.getElementById("qInput");

  async function load() {
    const qs = new URLSearchParams();
    if (exitFilter.value !== "") qs.set("exit_status", exitFilter.value);
    if (qInput.value.trim()) qs.set("q", qInput.value.trim());
    const res = await P16.api("/api/sys/job_execution_history?" + qs.toString());
    body.innerHTML = "";
    empty.style.display = res.data.length ? "none" : "block";
    res.data.forEach(function (r) {
      const tr = document.createElement("tr");
      const cls = r.exit_status === 0 ? "ok" : "error";
      tr.innerHTML =
        "<td>" + r.id + "</td>" +
        "<td><strong>" + P16.esc(r.job_name) + "</strong><div class='metrics-note'>" + P16.esc(r.profile) + "</div></td>" +
        "<td>" + r.run_number + "</td>" +
        "<td><span class='pill pill-" + cls + "'>" + r.exit_status + "</span></td>" +
        "<td>" + r.retry_count + "</td>" +
        "<td>" + P16.fmtDur(r.duration_ms) + "</td>" +
        "<td class='mono'>" + P16.esc(r.started_at) + "</td>" +
        "<td class='mono' style='white-space:pre-wrap'>" + P16.esc(r.output) + "</td>";
      body.appendChild(tr);
    });
  }

  exitFilter.addEventListener("change", load);
  qInput.addEventListener("keydown", function (e) { if (e.key === "Enter") load(); });
  document.getElementById("btnSearch").addEventListener("click", load);

  document.getElementById("formRun").addEventListener("submit", function (e) {
    e.preventDefault();
    P16.submitForm(this, {
      onSuccess: function (b) {
        this.reset();
        P16.toast("Run #" + b.data.run_number + " recorded for job #" + b.data.job_id + ".", "ok");
        load();
      }.bind(this),
    });
  });

  load();
});
</script>
