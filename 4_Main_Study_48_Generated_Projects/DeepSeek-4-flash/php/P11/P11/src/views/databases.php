<?php
/**
 * Database management (HOST-05). Sections: list | form | detail.
 * Variables: $section, $databases, $database
 */
if ($section === 'list'): ?>
    <div class="toolbar">
        <a class="btn btn-primary" href="/databases/new">Create database</a>
    </div>
    <table class="table">
        <thead>
        <tr><th>Name</th><th>Users</th><th>Created</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($databases as $db): ?>
            <tr>
                <td><a href="/databases/<?= (int) $db['id'] ?>"><?= e($db['name']) ?></a></td>
                <td><?= (int) ($db['user_count'] ?? 0) ?></td>
                <td><?= e($db['created_at']) ?></td>
                <td>
                    <a class="btn btn-small" href="/databases/<?= (int) $db['id'] ?>">Manage</a>
                    <form method="post" action="/databases/<?= (int) $db['id'] ?>/delete" class="inline" onsubmit="return confirm('Delete this database and its users?');">
                        <button class="btn btn-small btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif ($section === 'form'): ?>
    <form method="post" action="/databases" class="form form-narrow">
        <label>Database name
            <input type="text" name="name" placeholder="my_database" required>
        </label>
        <button class="btn btn-primary" type="submit">Create database</button>
    </form>
<?php elseif ($section === 'detail'): ?>
    <div class="toolbar">
        <a class="btn btn-small" href="/databases">Back</a>
    </div>
    <p class="muted">Database: <code><?= e($database['name']) ?></code> · created <?= e($database['created_at']) ?></p>

    <h2>Database users</h2>
    <table class="table">
        <thead>
        <tr><th>Username</th><th>Host</th><th>Privileges</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($database['users'] as $u): ?>
            <tr>
                <td><?= e($u['username']) ?></td>
                <td><?= e($u['host']) ?></td>
                <td><?= e($u['privileges']) ?></td>
                <td>
                    <form method="post" action="/databases/<?= (int) $database['id'] ?>/users/<?= (int) $u['id'] ?>/delete" class="inline">
                        <button class="btn btn-small btn-danger" type="submit">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Add database user</h3>
    <form method="post" action="/databases/<?= (int) $database['id'] ?>/users" class="form form-narrow">
        <label>Username
            <input type="text" name="username" required>
        </label>
        <label>Password (min 8 characters)
            <input type="password" name="password" required>
        </label>
        <label>Host
            <input type="text" name="host" value="localhost">
        </label>
        <label>Privileges
            <input type="text" name="privileges" value="ALL">
        </label>
        <button class="btn btn-primary" type="submit">Add user</button>
    </form>
<?php endif; ?>
