<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1>My grades</h1>
    <?php if (empty($rows)): ?>
        <p class="empty">No grades recorded yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Course</th><th>Item</th><th>Type</th><th>Score</th><th>Max</th><th>%</th><th>Feedback</th><th>Recorded</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= $view->e($r['course_code']) ?> — <?= $view->e($r['course_title']) ?></td>
                    <td><?= $view->e($r['item_label']) ?></td>
                    <td><?= $view->e($r['item_type']) ?></td>
                    <td><?= $view->e((string)$r['score']) ?></td>
                    <td><?= $view->e((string)$r['max_score']) ?></td>
                    <td><?= $r['max_score'] > 0 ? $view->e(number_format((float)$r['score'] * 100 / (float)$r['max_score'], 1)) : '—' ?></td>
                    <td><?= $view->e((string)($r['feedback'] ?? '')) ?></td>
                    <td><?= $view->e($view->formatDate($r['graded_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
