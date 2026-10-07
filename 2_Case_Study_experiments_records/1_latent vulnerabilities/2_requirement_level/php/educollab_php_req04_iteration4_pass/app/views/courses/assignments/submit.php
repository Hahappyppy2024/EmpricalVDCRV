<h1>Submit Assignment</h1>
<?php if (!empty($course) && !empty($assignment)): ?>
    <p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Assignment:</strong> <?= htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/assignments/<?= htmlspecialchars((string) $assignment['assignment_id'], ENT_QUOTES, 'UTF-8') ?>/submissions">
    <label>Content Text</label>
    <textarea name="content_text" rows="6"></textarea>
    <label>Attachment Upload</label>
    <select name="attachment_upload_id">
        <option value="">None</option>
        <?php foreach ($uploads as $upload): ?>
            <option value="<?= htmlspecialchars((string) $upload['upload_id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($upload['original_name'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
    </select>
    <?php if (!$uploads): ?><p>No eligible uploads are available for this submission.</p><?php endif; ?>
    <button type="submit">Create submission</button>
</form>

<h2>My submissions for this assignment</h2>
<?php if (!$my_submissions): ?><p>No submissions yet.</p><?php endif; ?>
<?php foreach ($my_submissions as $submission): ?>
    <div class="card">
        <p><?= nl2br(htmlspecialchars($submission['content_text'], ENT_QUOTES, 'UTF-8')) ?></p>
        <p><strong>Attachment:</strong> <?= htmlspecialchars((string) ($submission['attachment_original_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/assignments/<?= htmlspecialchars((string) $assignment['assignment_id'], ENT_QUOTES, 'UTF-8') ?>/submissions/<?= htmlspecialchars((string) $submission['submission_id'], ENT_QUOTES, 'UTF-8') ?>">
            <label>Update Content Text</label>
            <textarea name="content_text" rows="4"><?= htmlspecialchars($submission['content_text'], ENT_QUOTES, 'UTF-8') ?></textarea>
            <label>Attachment Upload</label>
            <select name="attachment_upload_id">
                <option value="">None</option>
                <?php foreach ($uploads as $upload): ?>
                    <option value="<?= htmlspecialchars((string) $upload['upload_id'], ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($submission['attachment_upload_id'] ?? '') === (string) $upload['upload_id'] ? 'selected' : '' ?>><?= htmlspecialchars($upload['original_name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!$uploads): ?><p>No eligible uploads are available for this submission.</p><?php endif; ?>
            <button type="submit">Update submission</button>
        </form>
    </div>
<?php endforeach; ?>