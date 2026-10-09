<section class="card">
    <h2>Activity</h2>
    <form method="post" action="/api/file/audit_log_and_exports" class="api-form" data-method="post" data-redirect="/activity">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label>Format
            <select name="format">
                <option value="csv">CSV</option>
                <option value="json">JSON</option>
            </select>
        </label>
        <label>From <input type="date" name="from_date"></label>
        <label>To <input type="date" name="to_date"></label>
        <button type="submit">Export</button>
    </form>
    <h3>Recent events</h3>
    <ul>
    <?php foreach ($events as $event): ?>
        <li><?= htmlspecialchars($event['created_at']) ?> &mdash; <?= htmlspecialchars($event['action']) ?> / <?= htmlspecialchars($event['entity_type']) ?></li>
    <?php endforeach; ?>
    </ul>
    <h3>Exports</h3>
    <ul>
    <?php foreach ($exports as $export): ?>
        <li>#<?= (int)$export['id'] ?> <?= htmlspecialchars($export['format']) ?> <?= htmlspecialchars($export['status']) ?> <?php if (!empty($export['file_id'])): ?><a href="/audit/<?= (int)$export['file_id'] ?>/download">Download</a><?php endif; ?></li>
    <?php endforeach; ?>
    </ul>
</section>