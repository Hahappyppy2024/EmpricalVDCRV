<?php /** @var array $rows */ ?>
<header class="page-header">
    <h1>Configuration editor</h1>
    <p class="muted">SYS-08 · admin only · pending changes must be approved before they replace the live value.</p>
</header>

<section class="card form">
    <h3>Add or update key</h3>
    <form method="post" action="/configuration">
        <div class="grid grid-2">
            <label>Key
                <input type="text" name="key" required>
            </label>
            <label>Category
                <input type="text" name="category" value="general" required>
            </label>
        </div>
        <label>Value
            <textarea name="value" rows="2" required></textarea>
        </label>
        <label>Description
            <textarea name="description" rows="2"></textarea>
        </label>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</section>

<section class="card">
    <h3>Configuration keys</h3>
    <table class="table">
        <thead><tr><th>Key</th><th>Value</th><th>Pending</th><th>Category</th><th>Updated</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['key']) ?></strong><div class="muted"><?= htmlspecialchars($row['description']) ?></div></td>
                    <td><code><?= htmlspecialchars($row['value']) ?></code></td>
                    <td>
                        <?php if ($row['pending_value'] !== null): ?>
                            <span class="badge badge-warning">pending</span>
                            <code><?= htmlspecialchars($row['pending_value']) ?></code>
                        <?php else: ?>
                            <span class="muted">none</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['category']) ?></td>
                    <td><?= htmlspecialchars((string)$row['updated_at']) ?></td>
                    <td>
                        <?php if ($row['pending_value'] !== null): ?>
                            <form method="post" action="/configuration/<?= (int)$row['id'] ?>/approve" class="inline">
                                <button class="btn btn-xs btn-primary" type="submit">Approve</button>
                            </form>
                            <form method="post" action="/configuration/<?= (int)$row['id'] ?>/reject" class="inline">
                                <button class="btn btn-xs btn-danger" type="submit">Reject</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="/configuration/<?= (int)$row['id'] ?>/stage" class="inline">
                            <input type="text" name="pending_value" placeholder="Stage new value">
                            <button class="btn btn-xs" type="submit">Stage</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>