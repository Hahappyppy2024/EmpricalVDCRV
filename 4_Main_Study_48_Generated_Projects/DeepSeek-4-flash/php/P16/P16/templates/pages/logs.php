<div class="page-head">
  <div>
    <h1>Log Viewer</h1>
    <div class="sub">Search, filter, preview and download application/system logs (SYS-03)</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="form-row">
      <label>Log file</label>
      <select id="fileSelect"></select>
    </div>
    <div class="form-row">
      <label>Level</label>
      <select id="levelSelect">
        <option value="">All levels</option>
        <option>debug</option><option>info</option><option>notice</option>
        <option>warning</option><option>error</option><option>critical</option>
      </select>
    </div>
    <div class="form-row flex">
      <label>Search message</label>
      <input type="text" id="qInput" placeholder="Filter by message, source or file…">
    </div>
    <div class="form-row">
      <button class="btn btn-primary" id="btnSearch" type="button">Search</button>
    </div>
    <div class="form-row">
      <a class="btn" id="btnDownload" href="#">Download</a>
    </div>
  </div>
</div>

<div class="card">
  <h2>Entries <span id="entryCount" class="pill pill-info" style="font-size:11px"></span></h2>
  <table>
    <thead>
      <tr><th style="width:170px">Time</th><th style="width:110px">Level</th><th style="width:140px">Source</th><th>Message</th><th style="width:60px"></th></tr>
    </thead>
    <tbody id="logBody"></tbody>
  </table>
  <div id="logEmpty" class="empty">Select a log file to preview its entries.</div>
</div>

<div class="card">
  <h2>Append log entry</h2>
  <form id="formLog" class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
    <div class="form-row">
      <label>File</label>
      <input type="text" name="file_name" placeholder="app.log" required>
    </div>
    <div class="form-row">
      <label>Level</label>
      <select name="level">
        <option>info</option><option>debug</option><option>notice</option>
        <option>warning</option><option>error</option><option>critical</option>
      </select>
    </div>
    <div class="form-row">
      <label>Source</label>
      <input type="text" name="source" value="manual" required>
    </div>
    <div class="form-row" style="grid-column:1/-1">
      <label>Message</label>
      <textarea name="message" required></textarea>
    </div>
    <div class="form-row" style="justify-content:end">
      <button type="submit" class="btn btn-primary">Append entry</button>
    </div>
  </form>
</div>

<script>
P16.onload(function () {
  const fileSelect = document.getElementById("fileSelect");
  const levelSelect = document.getElementById("levelSelect");
  const qInput = document.getElementById("qInput");
  const body = document.getElementById("logBody");
  const empty = document.getElementById("logEmpty");
  const count = document.getElementById("entryCount");

  async function loadFiles() {
    const res = await P16.api("/api/sys/log_viewer/files");
    fileSelect.innerHTML = '<option value="">— choose log file —</option>' +
      res.data.map(function (f) {
        return '<option value="' + P16.esc(f.file_name) + '">' + P16.esc(f.file_name) +
          " (" + f.entry_count + " entries)</option>";
      }).join("");
  }

  async function search() {
    const file = fileSelect.value;
    if (!file) return;
    const q = qInput.value.trim();
    const url = "/api/sys/log_viewer/preview?file=" + encodeURIComponent(file) + "&q=" + encodeURIComponent(q);
    const res = await P16.api(url);
    let rows = res.data;
    const level = levelSelect.value;
    if (level) rows = rows.filter(function (r) { return r.level === level; });
    count.textContent = rows.length + " shown";
    body.innerHTML = "";
    empty.style.display = rows.length ? "none" : "block";
    rows.forEach(function (r) {
      const tr = document.createElement("tr");
      tr.innerHTML =
        "<td class='mono'>" + P16.esc(r.created_at) + "</td>" +
        "<td>" + P16.badge(r.level) + "</td>" +
        "<td>" + P16.esc(r.source) + "</td>" +
        "<td class='mono'>" + P16.esc(r.message) + "</td>" +
        "<td><button class='btn btn-sm' data-id='" + r.id + "'>Edit</button></td>";
      body.appendChild(tr);
    });
    document.getElementById("btnDownload").href =
      "/api/sys/log_viewer/download?file=" + encodeURIComponent(file);
  }

  body.addEventListener("click", function (ev) {
    const btn = ev.target.closest("button[data-id]");
    if (!btn) return;
    const id = btn.dataset.id;
    const msg = prompt("Edit log message:");
    if (msg === null) return;
    P16.api("/api/sys/log_viewer/" + id, { method: "PATCH", body: { message: msg } })
      .then(function () { P16.toast("Entry #" + id + " updated.", "ok"); search(); })
      .catch(function (e) { P16.toast(e.message, "err"); });
  });

  fileSelect.addEventListener("change", search);
  levelSelect.addEventListener("change", search);
  document.getElementById("btnSearch").addEventListener("click", search);
  qInput.addEventListener("keydown", function (e) { if (e.key === "Enter") search(); });

  document.getElementById("formLog").addEventListener("submit", function (e) {
    e.preventDefault();
    P16.submitForm(this, {
      onSuccess: function (b) {
        this.reset();
        P16.toast("Log entry #" + b.data.id + " appended.", "ok");
        loadFiles();
        search();
      }.bind(this),
    });
  });

  loadFiles();
});
</script>
