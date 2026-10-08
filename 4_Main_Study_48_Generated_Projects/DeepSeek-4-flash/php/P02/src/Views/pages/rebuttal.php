<?php
namespace App\Views;
?>
<h1>Rebuttal</h1>

<?php if ($user['role'] === 'author'): ?>
  <section class="card">
    <h2>New rebuttal</h2>
    <?php if (($rebuttable ?? []) === []): ?>
      <p class="muted">No submissions of yours are currently in the rebuttal phase.</p>
    <?php else: ?>
      <form data-api-form data-json="true" method="post" action="/api/conf/rebuttal" data-refresh="true" class="stack">
        <label>Submission
          <select name="submission_id" required>
            <?php foreach ($rebuttable as $s): ?>
              <option value="<?= (int) $s['id'] ?>"><?= e($s['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Rebuttal text
          <textarea name="text" rows="6" required></textarea>
        </label>
        <button class="btn btn--primary" type="submit">Submit rebuttal</button>
      </form>
    <?php endif; ?>
  </section>
<?php elseif ($user['role'] === 'reviewer'): ?>
  <section class="card">
    <h2>Author rebuttals on your assignments</h2>
    <p class="muted">As a reviewer you may view allowed rebuttal responses.</p>
  </section>
<?php endif; ?>

<section class="card">
  <h2><?= $user['role'] === 'reviewer' ? 'Allowed responses' : 'My rebuttals' ?></h2>
  <?php if (($rebuttals ?? []) === []): ?>
    <p class="muted">No rebuttals recorded.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>ID</th><th>Submission</th><th>Status</th><th>Text</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($rebuttals as $r): ?>
        <tr>
          <td><?= (int) $r['id'] ?></td>
          <td>
            <?= isset($r['submission_title']) ? e($r['submission_title']) : '#' . (int) $r['submission_id'] ?>
          </td>
          <td><?= status_label($r['status']) ?></td>
          <td><?= nl2br(e(mb_strimwidth($r['text'], 0, 120, 'â€¦'))) ?></td>
          <td><?= date_fmt($r['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>