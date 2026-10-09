<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1>Roster — <?= $view->e($course['code']) ?> <?= $view->e($course['title']) ?></h1>
    <table class="table">
        <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Enrolled</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= $view->e($r['full_name']) ?></td>
                <td><?= $view->e($r['username']) ?></td>
                <td><?= $view->e($r['email']) ?></td>
                <td><?= $view->e($r['role']) ?></td>
                <td><?= $view->e($r['status']) ?></td>
                <td><?= $view->e($view->formatDate($r['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
