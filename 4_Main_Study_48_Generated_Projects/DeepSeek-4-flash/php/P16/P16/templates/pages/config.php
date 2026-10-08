<div class="page-head">
  <div>
    <h1>Configuration Editor</h1>
    <div class="sub">Edit approved configuration keys and review pending changes (SYS-08 · admin)</div>
  </div>
</div>

<div class="card">
  <h2>Add configuration key</h2>
  <form id="formKey" class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
    <div class="form-row">
      <label>Key</label>
      <input type="text" name="config_key" placeholder="poll_interval" required>
    </div>
    <div class="form-row">
      <label>Value</label>
      <input type="text" name="config_value" placeholder="30" required>
    </div>
    <div class="form-row">
      <label>Description</label>
      <input type="text" name="description" placeholder="Optional">
    </div>
    <div class="form-row" style="justify-content:end">
      <button type="submit" class="btn btn-primary">Add key</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>Configuration keys</h2>
  <div class="toolbar">
    <div class="form-row">
      <label>Status</label>
      <select id="statusFilter">
        <option value="">All</option>
        <option value="active">active</option>
        <option value="pending">pending</option>
      </select>
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th>Key</th><th>Value</th><th>Status</th><th>Description</th><th>Updated by</th><th style="width:200px">Actions</th>
      </tr>
    </thead>
    <tbody id="cfgBody"></tbody>
  </table>
  <div id="cfgEmpty" class="empty">No configuration keys.</div>
</div>

<script>
P16.onload(function () {
  const body = document.getElementById("cfgBody");
  const empty = document.getElementById("cfgEmpty");
  const statusFilter = document.getElementById("statusFilter");

  async function load() {
    const qs = new URLSearchParams();
    if (statusFilter.value) qs.set("status", statusFilter.value);
    const res = await P16.api("/api/sys/configuration_editor?" + qs.toString());
    body.innerHTML = "";
    empty.style.display = res.data.length ? "none" : "block";
    res.data.forEach(function (c) {
      const tr = document.createElement("tr");
      const pending = c.status === "pending";
      tr.innerHTML =
        "<td class='mono'><strong>" + P16.esc(c.config_key) + "</strong></td>" +
        "<td class='mono'>" + P16.esc(c.config_value) +
        (pending ? "<div class='metrics-note'>pending: <b>" + P16.esc(c.pending_value) + "</b></div>" : "") + "</td>" +
        "<td>" + P16.badge(c.status) + "</td>" +
        "<td>" + P16.esc(c.description) + "</td>" +
        "<td>" + P16.esc(c.updated_by_name || "—") + "</td>" +
        "<td>" +
        '<button class="btn btn-sm" data-edit="' + c.id + '">Edit</button> ' +
        (pending
          ? '<button class="btn btn-sm btn-success" data-review="approve" data-id="' + c.id + '">Approve</button> ' +
            '<button class="btn btn-sm btn-danger" data-review="reject" data-id="' + c.id + '">Reject</button> '
          : "") +
        '<button class="btn btn-sm btn-danger" data-del="' + c.id + '">Delete</button>' +
        "</td>";
      body.appendChild(tr);
    });
  }

  body.addEventListener("click", function (ev) {
    const edit = ev.target.closest("button[data-edit]");
    const review = ev.target.closest("button[data-review]");
    const del = ev.target.closest("button[data-del]");
    if (edit) {
      const val = prompt("New value (creates a pending change):");
      if (val === null) return;
      P16.api("/api/sys/configuration_editor/" + edit.dataset.edit, { method: "PATCH", body: { config_value: val } })
        .then(function () { P16.toast("Pending change proposed.", "ok"); load(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    } else if (review) {
      P16.api("/api/sys/configuration_editor/" + review.dataset.id, { method: "PATCH", body: { action: review.dataset.review } })
        .then(function () { P16.toast("Change " + review.dataset.review + "d.", "ok"); load(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    } else if (del) {
      if (!confirm("Delete this config key?")) return;
      P16.api("/api/sys/configuration_editor/" + del.dataset.id, { method: "DELETE" })
        .then(function () { P16.toast("Key deleted.", "ok"); load(); })
        .catch(function (e) { P16.toast(e.message, "err"); });
    }
  });

  statusFilter.addEventListener("change", load);

  document.getElementById("formKey").addEventListener("submit", function (e) {
    e.preventDefault();
    P16.submitForm(this, {
      onSuccess: function () { this.reset(); load(); }.bind(this),
    });
  });

  load();
});
</script>
