<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1>Submissions — <?= $view->e($assignment['title']) ?></h1>
    <?php if (empty($rows)): ?>
        <p class="empty">No submissions yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Student</th><th>Submitted</th><th>Status</th><th>File</th><th>Score</th><th>Grade</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $s): ?>
                <tr>
                    <td><?= $view->e($s['student_name']) ?></td>
                    <td><?= $view->e($view->formatDate($s['submitted_at'])) ?></td>
                    <td><?= $view->e($s['status']) ?></td>
                    <td><?= $view->e($s['original_name']) ?></td>
                    <td><?= $s['score'] !== null ? $view->e((string)$s['score']) : '—' ?></td>
                    <td>
                        <form method="post" action="/submissions/<?= (int)$s['id'] ?>/grade">
                            <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
                            <input type="number" step="0.1" name="score" value="<?= $s['score'] !== null ? $view->e((string)$s['score']) : '' ?>" required>
                            <input name="feedback" placeholder="feedback" value="<?= $view->e((string)($s['feedback'] ?? '')) ?>">
                            <button class="btn" type="submit">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
