<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1>Attempts — <?= $view->e($quiz['title']) ?></h1>
    <table class="table">
        <thead><tr><th>Student</th><th>Started</th><th>Submitted</th><th>Status</th><th>Score</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= $view->e($r['student_name']) ?></td>
                <td><?= $view->e($view->formatDate($r['started_at'])) ?></td>
                <td><?= $view->e($view->formatDate($r['submitted_at'])) ?></td>
                <td><?= $view->e($r['status']) ?></td>
                <td><?= $r['score'] !== null ? $view->e((string)$r['score']) . ' / ' . $view->e((string)$r['max_score']) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
