<?php /** @var \LMS\Http\View $view */ ?>
<section class="page narrow">
    <h1>Edit settings</h1>
    <form method="post" action="/admin/settings/edit" class="card">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Key (snake_case)
            <input name="key" required pattern="[a-z_]{2,40}">
        </label>
        <label>Value <input name="value" required></label>
        <button class="btn btn-primary" type="submit">Save</button>
    </form>
    <h2>Existing</h2>
    <table class="table">
        <?php foreach ($settings as $key => $value): ?>
            <tr><td><?= $view->e($key) ?></td><td><?= $view->e($value) ?></td></tr>
        <?php endforeach; ?>
    </table>
</section>
