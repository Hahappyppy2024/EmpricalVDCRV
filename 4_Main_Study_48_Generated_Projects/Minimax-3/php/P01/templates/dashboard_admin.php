<?php /** @var \LMS\Http\View $view */ ?>
<section class="dashboard">
    <header class="welcome">
        <h1>Administration</h1>
        <p class="muted">Hello <?= $view->e($user['full_name']) ?>, <?= (int)$userCount ?> users · <?= (int)$courseCount ?> courses</p>
    </header>
    <div class="grid">
        <a class="card" href="/admin/users">Users</a>
        <a class="card" href="/admin/courses">Courses</a>
        <a class="card" href="/admin/reports/bulk">Bulk reports</a>
        <a class="card" href="/admin/settings">Settings</a>
        <a class="card" href="/admin/audit">Audit log</a>
        <a class="card" href="/frontend-api">Frontend API</a>
    </div>
    <section class="card">
        <h2>Recent audit events</h2>
        <table class="table">
            <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Target</th></tr></thead>
            <tbody>
            <?php foreach ($audit as $a): ?>
                <tr>
                    <td><?= $view->e($view->formatDate($a['created_at'])) ?></td>
                    <td><?= $view->e($a['actor_name'] ?? '—') ?></td>
                    <td><?= $view->e($a['action']) ?></td>
                    <td><?= $view->e($a['target_type']) ?>#<?= (int)$a['target_id'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</section>
