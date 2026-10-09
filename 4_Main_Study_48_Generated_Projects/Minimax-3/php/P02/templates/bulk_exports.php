<h1>Bulk Exports</h1>
<?php if (!empty($errors)): ?><div class="alert error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!empty($success)): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<section class="card">
  <h2>Generate export</h2>
  <form method="post" action="/bulk_exports/run">
    <label>Scope
      <select name="scope">
        <option value="submissions">Submissions</option>
        <option value="reviews">Reviews</option>
        <option value="decisions">Decisions</option>
      </select>
    </label>
    <label>Format
      <select name="format">
        <option value="csv">CSV</option>
        <option value="tsv">TSV</option>
      </select>
    </label>
    <button type="submit" class="btn">Run export</button>
  </form>
</section>

<section>
  <h2>Recent exports</h2>
  <?php if (empty($jobs)): ?>
    <p>No exports yet.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>When</th><th>Scope</th><th>Format</th><th>Records</th><th>Download</th></tr></thead>
      <tbody>
        <?php foreach ($jobs as $j): ?>
          <tr>
            <td><?= htmlspecialchars($j['created_at']) ?></td>
            <td><?= htmlspecialchars($j['scope']) ?></td>
            <td><?= htmlspecialchars($j['format']) ?></td>
            <td><?= (int)$j['record_count'] ?></td>
            <td><a href="/bulk_exports/<?= (int)$j['id'] ?>/download">Download</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>