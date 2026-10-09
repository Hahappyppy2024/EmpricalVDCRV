<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <header class="page-header">
        <h1>Quizzes — <?= $view->e($course['code']) ?></h1>
        <?php if (!empty($canCreate)): ?>
            <a class="btn btn-primary" href="/courses/<?= (int)$course['id'] ?>/quizzes/new">New quiz</a>
        <?php endif; ?>
    </header>
    <?php if (empty($rows)): ?>
        <p class="empty">No quizzes yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Title</th><th>Closes</th><th>Questions</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $q): ?>
                <tr>
                    <td><?= $view->e($q['title']) ?></td>
                    <td><?= $view->e($view->formatDate($q['closes_at'])) ?></td>
                    <td><?= (int)$q['question_count'] ?></td>
                    <td>
                        <?php if (($user['role'] ?? '') === 'student'): ?>
                            <a class="btn" href="/quizzes/<?= (int)$q['id'] ?>/take">Take quiz</a>
                        <?php else: ?>
                            <a class="btn" href="/quizzes/<?= (int)$q['id'] ?>/attempts">Attempts</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
