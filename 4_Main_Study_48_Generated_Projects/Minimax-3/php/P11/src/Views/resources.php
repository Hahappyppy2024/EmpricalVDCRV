<h1>Resource usage</h1>
<section class="card">
  <h2>Summary</h2>
  <ul class="kv">
    <li><span>Avg CPU</span><b><?= htmlspecialchars((string)$summary['avg_cpu']) ?>%</b></li>
    <li><span>Max CPU</span><b><?= htmlspecialchars((string)$summary['max_cpu']) ?>%</b></li>
    <li><span>Avg disk</span><b><?= htmlspecialchars((string)$summary['avg_disk']) ?> MB</b></li>
    <li><span>Max disk</span><b><?= htmlspecialchars((string)$summary['max_disk']) ?> MB</b></li>
    <li><span>Total bandwidth</span><b><?= htmlspecialchars((string)$summary['total_bw']) ?> MB</b></li>
    <li><span>Plan disk quota</span><b><?= htmlspecialchars((string)$summary['plan_disk_mb']) ?> MB</b></li>
    <li><span>Plan bandwidth quota</span><b><?= htmlspecialchars((string)$summary['plan_bw_mb']) ?> MB</b></li>
    <li><span>Emails sent</span><b><?= htmlspecialchars((string)$summary['total_emails']) ?></b></li>
  </ul>
</section>

<section class="card">
  <h2>Add usage report</h2>
  <form method="post" action="/resources" class="form inline">
    <label>Period <input type="date" name="period" value="<?= date('Y-m-d') ?>" required></label>
    <label>CPU % <input type="number" name="cpu_percent" step="0.1" min="0" max="100" required></label>
    <label>Disk MB <input type="number" name="disk_used_mb" min="0" required></label>
    <label>Bandwidth MB <input type="number" name="bandwidth_used_mb" min="0" required></label>
    <label>Emails <input type="number" name="emails_sent" min="0" required></label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Record</button>
  </form>
</section>

<section class="card">
  <h2>History</h2>
  <?php if (!$series): ?>
    <p class="muted">No usage data.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Period</th><th>CPU%</th><th>Disk MB</th><th>Bandwidth MB</th><th>Emails</th><th>Recorded</th></tr></thead>
    <tbody>
      <?php foreach ($series as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['period']) ?></td>
        <td><?= htmlspecialchars((string)$r['cpu_percent']) ?></td>
        <td><?= (int)$r['disk_used_mb'] ?></td>
        <td><?= (int)$r['bandwidth_used_mb'] ?></td>
        <td><?= (int)$r['emails_sent'] ?></td>
        <td><?= htmlspecialchars($r['recorded_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>