<?php
/**
 * Audit logs (HOST-11). Variables: $events, $filters, $actions, $isAdmin, $error
 */
?>
<form method="get" action="/audit" class="form form-inline">
    <label>Module
        <input type="text" name="module" value="<?= e($filters['module']) ?>" placeholder="e.g. domain_management">
    </label>
    <label>Action
        <select name="action">
            <option value="">All</option>
            <?php foreach ($actions as $a): ?>
                <option value="<?= e($a) ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if ($isAdmin): ?>
        <label>Username
            <input type="text" name="username" value="<?= e($filters['username']) ?>">
        </label>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-small" href="/audit">Reset</a>
</form>

<?php if ($error !== null): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<table class="table" id="audit-table">
    <thead>
    <tr><th>#</th><th>User</th><th>Action</th><th>Module</th><th>Entity</th><th>Details</th><th>When</th></tr>
    </thead>
    <tbody>
    <?php foreach ($events as $ev): ?>
        <tr>
            <td><?= (int) $ev['id'] ?></td>
            <td><?= e($ev['username']) ?></td>
            <td><span class="badge <?= badge($ev['action']) ?>"><?= e($ev['action']) ?></span></td>
            <td><?= e($ev['module']) ?></td>
            <td><?= e($ev['entity_type']) ?> #<?= e($ev['entity_id']) ?></td>
            <td><?= e($ev['details']) ?></td>
            <td><?= e($ev['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($events === []): ?>
        <tr><td colspan="7" class="muted">No audit events match the current filters.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<p class="muted">Live updates are pushed by the WebSocket process (ws://localhost:8081). Connect to see new events in real time.</p>
