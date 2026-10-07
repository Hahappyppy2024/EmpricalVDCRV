<div class="card">
    <h2>Submit: <?= h($assignment['title']) ?></h2>
    <p class="muted">course <?= h($course_id) ?> | assignment <?= h($assignment['assignment_id']) ?></p>

    <?php if ($submission): ?>
        <p class="muted">Existing submission updated at <?= h($submission['updated_at']) ?></p>
    <?php endif; ?>

    <form method="post" action="/courses/<?= h($course_id) ?>/assignments/<?= h($assignment['assignment_id']) ?>/submit">
        <?= csrf_input() ?>
        <label>Content</label>
        <textarea name="content_text"><?= h($submission['content_text'] ?? '') ?></textarea>
        <label>Attachment Upload ID (optional)</label>
        <input type="number" name="attachment_upload_id" value="<?= h((string)($submission['attachment_upload_id'] ?? '')) ?>" />
        <div style="margin-top:12px"><button type="submit">Save Submission</button></div>
    </form>
</div>