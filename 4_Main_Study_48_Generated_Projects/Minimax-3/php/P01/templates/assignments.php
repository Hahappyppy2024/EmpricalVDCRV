<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <header class="page-header">
        <h1>Assignments — <?= $view->e($course['code']) ?></h1>
        <?php if (!empty($canCreate)): ?>
            <a class="btn btn-primary" href="/courses/<?= (int)$course['id'] ?>/assignments/new">New assignment</a>
        <?php endif; ?>
    </header>
    <?php if (empty($rows)): ?>
        <p class="empty">No assignments yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Title</th><th>Due</th><th>Max score</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $a): ?>
                <tr>
                    <td><?= $view->e($a['title']) ?></td>
                    <td><?= $view->e($view->formatDate($a['due_at'])) ?></td>
                    <td><?= $view->e((string)$a['max_score']) ?></td>
                    <td>
                        <?php if (($user['role'] ?? '') === 'student'): ?>
                            <?php if (!empty($a['submission_id'])): ?>
                                <span class="badge <?= $view->e($a['submission_status']) ?>"><?= $view->e($a['submission_status']) ?></span>
                                <?php if ($a['submission_score'] !== null): ?>
                                    (<?= $view->e((string)$a['submission_score']) ?>)
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge">pending</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= (int)$a['submission_count'] ?> submissions
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (($user['role'] ?? '') === 'student'): ?>
                            <a class="btn" href="/assignments/<?= (int)$a['id'] ?>/submit">Submit</a>
                        <?php else: ?>
                            <a class="btn" href="/assignments/<?= (int)$a['id'] ?>/submissions">Review</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
