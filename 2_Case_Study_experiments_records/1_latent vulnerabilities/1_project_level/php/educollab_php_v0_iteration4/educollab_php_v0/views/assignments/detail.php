<div class="card">
    <h2><?= h($assignment['title']) ?></h2>
    <p><?= h($assignment['description']) ?></p>
    <p class="muted">due <?= h($assignment['due_at'] ?? '') ?> | created_by <?= h($assignment['creator_username'] ?? '') ?></p>

    <div style="margin:12px 0">
        <a href="/courses/<?= h($course_id) ?>/assignments/<?= h($assignment['assignment_id']) ?>/edit">Edit</a>
        <form style="display:inline" method="post" action="/courses/<?= h($course_id) ?>/assignments/<?= h($assignment['assignment_id']) ?>/delete">
            <?= csrf_input() ?>
            <button type="submit">Delete</button>
        </form>
        <a href="/courses/<?= h($course_id) ?>/assignments/<?= h($assignment['assignment_id']) ?>/submit">Submit / Update Submission</a>
    </div>

    <h3>Submissions</h3>
    <?php if (empty($submissions)): ?>
        <p class="muted">No submissions yet.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>submission_id</th><th>student</th><th>updated_at</th><th>text</th><th>attachment</th></tr></thead>
            <tbody>
            <?php foreach ($submissions as $s): ?>
                <tr>
                    <td><?= h((string)$s['submission_id']) ?></td>
                    <td><?= h($s['student_username'] ?? '') ?></td>
                    <td><?= h($s['updated_at'] ?? '') ?></td>
                    <td><?= h(snippet($s['content_text'] ?? '')) ?></td>
                    <td><?= h((string)($s['attachment_upload_id'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>