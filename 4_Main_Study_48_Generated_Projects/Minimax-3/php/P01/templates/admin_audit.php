<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1>Audit log</h1>
    <table class="table">
        <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Target</th><th>Payload</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= $view->e($view->formatDate($r['created_at'])) ?></td>
                <td><?= $view->e($r['actor_name'] ?? '—') ?></td>
                <td><?= $view->e($r['action']) ?></td>
                <td><?= $view->e($r['target_type']) ?>#<?= (int)$r['target_id'] ?></td>
                <td><code class="payload"><?= $view->e($r['payload_json']) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
