<div class="card">
  <h2>Submit: <?= h($assignment['title']) ?></h2>
  <p class="muted">course <?= h($course_id) ?> | assignment <?= h($assignment['assignment_id']) ?></p>

  <?php if ($submission): ?>
    <h3>Update my submission</h3>
    <form method="post" action="/courses/<?= h($course_id) ?>/assignments/<?= h($assignment['assignment_id']) ?>/submissions/<?= h($submission['submission_id']) ?>">
      <label>Content</label>
      <textarea name="content_text" rows="5"><?= h($submission['content_text']) ?></textarea>
      <label>Attachment upload_id (optional)</label>
      <select name="attachment_upload_id">
        <option value="">(none)</option>
        <?php foreach ($uploads as $up): ?>
          <option value="<?= h($up['upload_id']) ?>" <?= ((string)($submission['attachment_upload_id'] ?? '') === (string)$up['upload_id'])?'selected':'' ?>>#<?= h($up['upload_id']) ?> <?= h($up['original_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Update</button>
    </form>
  <?php else: ?>
    <h3>Create my submission</h3>
    <form method="post" action="/courses/<?= h($course_id) ?>/assignments/<?= h($assignment['assignment_id']) ?>/submissions">
      <label>Content</label>
      <textarea name="content_text" rows="5"></textarea>
      <label>Attachment upload_id (optional)</label>
      <select name="attachment_upload_id">
        <option value="">(none)</option>
        <?php foreach ($uploads as $up): ?>
          <option value="<?= h($up['upload_id']) ?>">#<?= h($up['upload_id']) ?> <?= h($up['original_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Submit</button>
    </form>
  <?php endif; ?>

  <p class="muted">Tip: staff can upload files in Uploads module; students can reference upload_id as attachment.</p>
</div>
