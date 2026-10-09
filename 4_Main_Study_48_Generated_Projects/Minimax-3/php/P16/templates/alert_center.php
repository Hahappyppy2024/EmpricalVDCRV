<?php /** @var array $alerts */ /** @var array $operators */ /** @var string|null $severity */ /** @var string|null $state */ ?>
<header class="page-header">
    <h1>Alert center</h1>
    <p class="muted">SYS-09 · view, acknowledge, assign, comment.</p>
</header>

<form method="get" action="/alerts" class="card form inline-form">
    <label>Severity
        <select name="severity">
            <option value="">all</option>
            <?php foreach (['info','warning','critical'] as $sev): ?>
                <option value="<?= $sev ?>" <?= $severity === $sev ? 'selected' : '' ?>><?= $sev ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>State
        <select name="state">
            <option value="">all</option>
            <?php foreach (['open','acknowledged','assigned','resolved','closed'] as $st): ?>
                <option value="<?= $st ?>" <?= $state === $st ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="btn">Filter</button>
</form>

<section class="card form">
    <h3>New alert</h3>
    <form method="post" action="/alerts">
        <div class="grid grid-2">
            <label>Title
                <input type="text" name="title" required>
            </label>
            <label>Severity
                <select name="severity">
                    <?php foreach (['info','warning','critical'] as $sev): ?>
                        <option value="<?= $sev ?>"><?= $sev ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>Message
            <textarea name="message" rows="2"></textarea>
        </label>
        <label>Source
            <input type="text" name="source" value="manual">
        </label>
        <button type="submit" class="btn btn-primary">Create</button>
    </form>
</section>

<section class="card">
    <h3>Alerts</h3>
    <table class="table">
        <thead><tr><th>#</th><th>Severity</th><th>Title</th><th>State</th><th>Assignee</th><th>Acknowledged by</th><th>Updated</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($alerts as $a): ?>
                <tr>
                    <td>#<?= (int)$a['id'] ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars($a['severity']) ?>"><?= htmlspecialchars($a['severity']) ?></span></td>
                    <td>
                        <?= htmlspecialchars($a['title']) ?>
                        <div class="muted"><?= htmlspecialchars($a['message']) ?></div>
                    </td>
                    <td><span class="badge badge-<?= htmlspecialchars($a['state']) ?>"><?= htmlspecialchars($a['state']) ?></span></td>
                    <td><?= htmlspecialchars((string)($a['assignee_name'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string)($a['acknowledger_name'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string)$a['updated_at']) ?></td>
                    <td>
                        <form method="post" action="/alerts/<?= (int)$a['id'] ?>" class="inline">
                            <input type="hidden" name="action" value="acknowledge">
                            <button class="btn btn-xs" type="submit">Ack</button>
                        </form>
                        <form method="post" action="/alerts/<?= (int)$a['id'] ?>" class="inline">
                            <input type="hidden" name="action" value="resolve">
                            <button class="btn btn-xs" type="submit">Resolve</button>
                        </form>
                        <form method="post" action="/alerts/<?= (int)$a['id'] ?>" class="inline">
                            <input type="hidden" name="action" value="close">
                            <button class="btn btn-xs" type="submit">Close</button>
                        </form>
                        <form method="post" action="/alerts/<?= (int)$a['id'] ?>" class="inline">
                            <input type="hidden" name="action" value="assign">
                            <select name="assignee_id">
                                <?php foreach ($operators as $op): ?>
                                    <option value="<?= (int)$op['id'] ?>"><?= htmlspecialchars($op['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-xs" type="submit">Assign</button>
                        </form>
                        <form method="post" action="/alerts/<?= (int)$a['id'] ?>" class="inline">
                            <input type="hidden" name="action" value="comment">
                            <input type="text" name="body" placeholder="Comment">
                            <button class="btn btn-xs" type="submit">Send</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>