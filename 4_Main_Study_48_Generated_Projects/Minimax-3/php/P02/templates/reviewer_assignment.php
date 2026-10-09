<h1>Reviewer Assignment</h1>

<section class="card">
  <h2>Assign reviewer</h2>
  <form method="post" action="/reviewer_assignment/assign">
    <label>Submission
      <select name="submission_id" required>
        <?php foreach ($submissions as $s): ?>
          <option value="<?= (int)$s['id'] ?>">#<?= (int)$s['id'] ?> - <?= htmlspecialchars($s['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Reviewer
      <select name="reviewer_id" required>
        <?php foreach ($reviewers as $r): ?>
          <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['display_name']) ?> (<?= htmlspecialchars($r['affiliation']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Due date <input type="date" name="due_date" required></label>
    <button type="submit" class="btn">Assign</button>
  </form>
</section>

<section class="card">
  <h2>Declare conflict of interest</h2>
  <form method="post" action="/reviewer_assignment/conflict">
    <label>Submission
      <select name="submission_id" required>
        <?php foreach ($submissions as $s): ?>
          <option value="<?= (int)$s['id'] ?>">#<?= (int)$s['id'] ?> - <?= htmlspecialchars($s['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Reviewer
      <select name="reviewer_id" required>
        <?php foreach ($reviewers as $r): ?>
          <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['display_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Reason <input type="text" name="reason" required></label>
    <button type="submit" class="btn">Declare</button>
  </form>
</section>

<section>
  <h2>Current assignments</h2>
  <table class="data-table">
    <thead><tr><th>Submission</th><th>Reviewer</th><th>Due</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($assignments as $a): ?>
        <tr>
          <td><?= htmlspecialchars($a['submission_title']) ?></td>
          <td><?= htmlspecialchars($a['reviewer_name']) ?></td>
          <td><?= htmlspecialchars(substr($a['due_date'], 0, 10)) ?></td>
          <td><span class="badge"><?= htmlspecialchars($a['status']) ?></span></td>
          <td>
            <form method="post" action="/reviewer_assignment/<?= (int)$a['id'] ?>/remove" style="display:inline">
              <button type="submit" class="btn btn-danger">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section>
  <h2>Declared conflicts</h2>
  <?php if (empty($conflicts)): ?>
    <p>No conflicts declared.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>Submission</th><th>Reviewer</th><th>Reason</th></tr></thead>
      <tbody>
        <?php foreach ($conflicts as $c): ?>
          <tr>
            <td><?= htmlspecialchars($c['submission_title']) ?></td>
            <td><?= htmlspecialchars($c['reviewer_name']) ?></td>
            <td><?= htmlspecialchars($c['reason']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>