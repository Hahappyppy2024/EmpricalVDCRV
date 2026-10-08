<div class="page-head">
  <div>
    <h1>Backup Manager</h1>
    <div class="sub">Create, download, upload, restore and delete backups (SYS-07)</div>
  </div>
</div>

<div class="card">
  <h2>Create backup</h2>
  <form id="formCreate" class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
    <div class="form-row">
      <label>Name</label>
      <input type="text" name="name" placeholder="pre-upgrade-v2" required>
    </div>
    <div class="form-row">
      <label>Type</label>
      <select name="backup_type">
        <option value="manual">manual</option>
        <option value="scheduled">scheduled</option>
      </select>
    </div>
    <div class="form-row" style="justify-content:end">
      <button type="submit" class="btn btn-primary">Create backup</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>Upload backup file</h2>
  <form id="formUpload" enctype="multipart/form-data">
    <div class="toolbar" style="margin-bottom:0">
      <div class="form-row">
        <label>Backup name</label>
        <input type="text" name="name" placeholder="restore-point">
      </div>
      <div class="form-row flex">
        <label>File (max 5 MB)</label>
        <input type="file" name="file" required>
      </div>
      <div class="form-row">
        <button type="submit" class="btn btn-primary">Upload</button>
      </div>
    </div>
  </form>
</div>

<div class="card">
  <h2>Backups</h2>
  <table>
    <thead>
      <tr>
        <th>Name</th><th>Type</th><th>Status</th><th>Size</th><th>SHA-256</th>
        <th>Owner</th><th>Created</th><th style="width:230px">Actions</th>
      </tr>
    </thead>
    <tbody id="backupBody"></tbody>
  </table>
  <div id="backupEmpty" class="empty">No backups yet.</div>
</div>

<script>
P16.onload(function () {
  const body = document.getElementById("backupBody");
  const empty = document.getElementById("backupEmpty");

  async function load() {
    const res = await P16.api("/api/sys/backup_manager");
    body.innerHTML = "";
    empty.style.display = res.data.length ? "none" : "block";
    res.data.forEach(function (b) {
      const tr = document.createElement("tr");
      tr.innerHTML =
        "<td><strong>" + P16.esc(b.name) + "</strong><div class='metrics-note'>" + P16.esc(b.original_name || "") + "</div></td>" +
        "<td>" + P16.esc(b.backup_type) + "</td>" +
        "<td>" + P16.badge(b.status) + "</td>" +
        "<td>" + P16.fmtBytes(b.size_bytes) + "</td>" +
        "<td class='mono' title='" + P16.esc(b.sha256 || "") + "'>" + (b.sha256 ? b.sha256.slice(0, 12) + "…" : "—") + "</td>" +
        "<td>" + P16.esc(b.owner_name) + "</td>" +
        "<td class='mono'>" + P16.esc(b.created_at) + "</td>" +
        "<td>" +
        '<a class="btn btn-sm" href="/api/sys/backup_manager/' + b.id + '/download">Download</a> ' +
        '<button class="btn btn-sm btn-success" data-restore="' + b.id + '"' + (b.status === "deleted" ? " disabled" : "") + ">Restore</button> " +
        '<button class="btn btn-sm btn-danger" data-del="' + b.id + '">Delete</button>' +
        "</td>";
      body.appendChild(tr);
    });
  }

  body.addEventListener("click", function (ev) {
    const restore = ev.target.closest("button[data-restore]");
    const del = ev.target.closest("button[data-del]");
    if (restore) {
      P16.api("/api/sys/backup_manager/" + restore.dataset.restore + "/restore", { method: "POST" })
        .then(function (b) { P16.toast("Backup " + b.data.name + " restored.", "ok"); load(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    } else if (del) {
      if (!confirm("Delete this backup and its stored file?")) return;
      P16.api("/api/sys/backup_manager/" + del.dataset.id, { method: "DELETE" })
        .then(function () { P16.toast("Backup deleted.", "ok"); load(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    }
  });

  document.getElementById("formCreate").addEventListener("submit", function (e) {
    e.preventDefault();
    P16.submitForm(this, {
      onSuccess: function () { this.reset(); load(); }.bind(this),
    });
  });

  document.getElementById("formUpload").addEventListener("submit", function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch("/api/sys/backup_manager/upload", { method: "POST", body: fd })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (res.ok) { P16.toast(res.j.message || "Backup uploaded.", "ok"); load(); this.reset(); }
        else P16.toast(res.j.error || "Upload failed.", "err");
      }.bind(this))
      .catch(function () { P16.toast("Upload failed.", "err"); });
  });

  load();
});
</script>
