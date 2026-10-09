<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1>Users</h1>
    <table class="table">
        <thead><tr><th>Username</th><th>Full name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $view->e($u['username']) ?></td>
                <td><?= $view->e($u['full_name']) ?></td>
                <td><?= $view->e($u['email']) ?></td>
                <td>
                    <form method="post" action="/admin/users/<?= (int)$u['id'] ?>/role">
                        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
                        <select name="role">
                            <?php foreach (['student','instructor','admin'] as $r): ?>
                                <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn" type="submit">Set role</button>
                    </form>
                </td>
                <td>
                    <form method="post" action="/admin/users/<?= (int)$u['id'] ?>/status">
                        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
                        <select name="status">
                            <?php foreach (['active','suspended','deleted'] as $s): ?>
                                <option value="<?= $s ?>" <?= $u['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn" type="submit">Set status</button>
                    </form>
                </td>
                <td></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
