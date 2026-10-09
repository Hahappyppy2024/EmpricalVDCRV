<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1>Settings</h1>
    <table class="table">
        <thead><tr><th>Key</th><th>Value</th><th>Updated</th></tr></thead>
        <tbody>
        <?php foreach ($settings as $key => $value): ?>
            <tr><td><?= $view->e($key) ?></td><td><?= $view->e($value) ?></td><td>—</td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <a class="btn btn-primary" href="/admin/settings/edit">Add / update setting</a>
</section>
