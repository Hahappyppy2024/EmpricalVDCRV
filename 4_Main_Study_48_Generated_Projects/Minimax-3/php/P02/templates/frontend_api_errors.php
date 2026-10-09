<h1>Frontend API Integration & Errors</h1>

<section class="card">
  <h2>Report an error</h2>
  <form id="errorReportForm" method="post" action="/frontend_api_integration_and_errors/report">
    <label>Code <input type="text" name="code" required></label>
    <label>Severity
      <select name="severity">
        <option value="info">Info</option>
        <option value="warning">Warning</option>
        <option value="error">Error</option>
        <option value="critical">Critical</option>
      </select>
    </label>
    <label>Source <input type="text" name="source" value="ui"></label>
    <label>Message <input type="text" name="message" required></label>
    <label>Details <textarea name="details" rows="3"></textarea></label>
    <button type="submit" class="btn">Report</button>
  </form>
</section>

<section>
  <h2>Recent error log</h2>
  <?php if (empty($errors)): ?>
    <p>No errors logged.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>When</th><th>Code</th><th>Severity</th><th>Source</th><th>Message</th><th>User</th></tr></thead>
      <tbody>
        <?php foreach ($errors as $e): ?>
          <tr>
            <td><?= htmlspecialchars($e['created_at']) ?></td>
            <td><?= htmlspecialchars($e['code']) ?></td>
            <td><span class="badge"><?= htmlspecialchars($e['severity']) ?></span></td>
            <td><?= htmlspecialchars($e['source']) ?></td>
            <td><?= htmlspecialchars($e['message']) ?></td>
            <td><?= htmlspecialchars($e['username'] ?? 'guest') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<script>
document.getElementById('errorReportForm')?.addEventListener('submit', function(ev) {
  ev.preventDefault();
  fetch(this.action, { method: 'POST', body: new FormData(this) })
    .then(r => r.json()).then(d => {
      alert(d.ok ? 'Error logged.' : 'Failed: ' + d.error);
      if (d.ok) location.reload();
    }).catch(() => alert('Network error'));
});
</script>