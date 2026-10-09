<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1>Recent exports</h1>
    <?php if (empty($rows)): ?>
        <p class="empty">No exports generated yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Created</th><th>Course</th><th>Format</th><th>File</th><th>Summary</th><th>Requester</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= $view->e($view->formatDate($r['created_at'])) ?></td>
                    <td><?= $view->e($r['course_title'] ?? '—') ?></td>
                    <td><?= $view->e($r['format']) ?></td>
                    <td><?= $view->e($r['file_path']) ?></td>
                    <td><?= $view->e($r['summary']) ?></td>
                    <td><?= $view->e($r['requester_name']) ?></td>
                    <td><a class="btn" href="/exports/<?= (int)$r['id'] ?>/download">Download</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
