<?php /** @var array $files */ /** @var array|null $file */ /** @var array $entries */ /** @var string $level */ /** @var string $filter */ ?>
<header class="page-header">
    <h1>Log viewer</h1>
    <p class="muted">SYS-03</p>
</header>

<form method="get" action="/logs" class="card form inline-form">
    <label>File
        <select name="file">
            <?php foreach ($files as $f): ?>
                <option value="<?= (int)$f['id'] ?>" <?= ($file && (int)$file['id'] === (int)$f['id']) ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Level
        <select name="level">
            <option value="">all</option>
            <?php foreach (['info','warning','error'] as $l): ?>
                <option value="<?= $l ?>" <?= $level === $l ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Contains
        <input type="text" name="q" value="<?= htmlspecialchars($filter) ?>">
    </label>
    <button type="submit" class="btn">Filter</button>
    <?php if ($file): ?>
        <a class="btn" href="/logs/download/<?= (int)$file['id'] ?>">Download</a>
    <?php endif; ?>
</form>

<section class="card">
    <?php if (!$file): ?>
        <p class="muted">Choose a log file.</p>
    <?php else: ?>
        <h3><?= htmlspecialchars($file['name']) ?> <span class="muted">· <?= htmlspecialchars($file['description']) ?></span></h3>
        <table class="table">
            <thead><tr><th>Time</th><th>Level</th><th>Message</th></tr></thead>
            <tbody>
                <?php foreach ($entries as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$e['ts']) ?></td>
                        <td><span class="badge badge-<?= htmlspecialchars($e['level']) ?>"><?= htmlspecialchars($e['level']) ?></span></td>
                        <td><?= htmlspecialchars((string)$e['message']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="3" class="muted">No matching entries.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>