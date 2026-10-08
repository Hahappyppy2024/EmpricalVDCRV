<div class="page-head">
  <div>
    <h1>Server Dashboard</h1>
    <div class="sub">CPU · memory · disk · uptime · service status (SYS-02)</div>
  </div>
  <button class="btn" id="btnRefresh" type="button">Refresh now</button>
</div>

<div id="dashGrid" class="grid cols-2"></div>

<div class="card">
  <h2>Record metric snapshot</h2>
  <form id="formSnapshot" class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
    <div class="form-row">
      <label>Server name</label>
      <input type="text" name="server_name" placeholder="web-02" required>
    </div>
    <div class="form-row">
      <label>Hostname</label>
      <input type="text" name="hostname" placeholder="web02.internal">
    </div>
    <div class="form-row">
      <label>CPU %</label>
      <input type="number" name="cpu_pct" min="0" max="100" step="0.1" required>
    </div>
    <div class="form-row">
      <label>Memory %</label>
      <input type="number" name="memory_pct" min="0" max="100" step="0.1" required>
    </div>
    <div class="form-row">
      <label>Disk %</label>
      <input type="number" name="disk_pct" min="0" max="100" step="0.1" required>
    </div>
    <div class="form-row">
      <label>Uptime (seconds)</label>
      <input type="number" name="uptime_seconds" min="0">
    </div>
    <div class="form-row">
      <label>Service status</label>
      <select name="service_status">
        <option value="ok">ok</option>
        <option value="degraded">degraded</option>
        <option value="down">down</option>
        <option value="maintenance">maintenance</option>
      </select>
    </div>
    <div class="form-row" style="justify-content:end">
      <button type="submit" class="btn btn-primary">Record snapshot</button>
    </div>
  </form>
</div>

<script>
P16.onload(function () {
  const grid = document.getElementById("dashGrid");
  const form = document.getElementById("formSnapshot");
  let socket = null;

  function bar(pct, label) {
    const cls = pct >= 90 ? "red" : pct >= 70 ? "yellow" : "green";
    return '<div class="form-row"><label>' + label + " (" + pct.toFixed(1) + "%)</label>" +
      '<div class="bar ' + cls + '"><span style="width:' + pct.toFixed(1) + '%"></span></div></div>';
  }

  function render(data) {
    grid.innerHTML = "";
    if (!data.length) {
      grid.innerHTML = '<div class="card empty">No metric snapshots recorded yet.</div>';
      return;
    }
    data.forEach(function (s) {
      const el = document.createElement("div");
      el.className = "card kpi";
      el.innerHTML =
        '<div style="display:flex;justify-content:space-between;align-items:center">' +
        '<div class="kpi-value" style="font-size:18px">' + P16.esc(s.server_name) + "</div>" +
        P16.badge(s.service_status) + "</div>" +
        '<div class="kpi-sub">' + P16.esc(s.hostname) + " · up " + P16.fmtUptime(s.uptime_seconds) + "</div>" +
        '<div class="grid" style="margin-top:10px">' +
        bar(Number(s.cpu_pct), "CPU") + bar(Number(s.memory_pct), "Memory") + bar(Number(s.disk_pct), "Disk") +
        "</div>" +
        '<div class="metrics-note">recorded ' + P16.esc(s.created_at) + " by " + P16.esc(s.recorded_by_name || "—") + "</div>";
      grid.appendChild(el);
    });
  }

  async function load() {
    try {
      const body = await P16.api("/api/sys/server_dashboard/latest");
      render(body.data);
    } catch (e) { P16.toast(e.message, "err"); }
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    P16.submitForm(form, {
      onSuccess: function (body) {
        form.reset();
        load();
        P16.toast("Metric snapshot #" + body.data.id + " recorded.", "ok");
      },
    });
  });

  document.getElementById("btnRefresh").addEventListener("click", load);

  // Optional Workerman WebSocket live updates.
  if (window.P16_WS_ENABLED === true && "WebSocket" in window) {
    try {
      socket = new WebSocket(window.P16_WS_URL);
      socket.onmessage = function (ev) {
        try {
          const msg = JSON.parse(ev.data);
          if (msg.type === "metrics") render(msg.data);
        } catch (_) { /* ignore malformed push */ }
      };
      socket.onclose = function () { P16.toast("Live WebSocket disconnected; using polling.", "warn"); };
    } catch (_) { /* no ws */ }
  }
  if (!socket) setInterval(load, 15000);
  load();
});
</script>
