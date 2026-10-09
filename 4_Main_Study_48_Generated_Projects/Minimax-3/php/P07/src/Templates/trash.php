<section class="card">
    <h2>Trash</h2>
    <table>
        <thead><tr><th>Item</th><th>Owner</th><th>Deleted</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['item_type']) ?> #<?= (int)$item['item_id'] ?> &mdash; <?= htmlspecialchars($item['original_name']) ?></td>
                <td><?= htmlspecialchars((string)$item['owner_id']) ?></td>
                <td><?= htmlspecialchars($item['created_at']) ?></td>
                <td>
                    <form method="post" class="api-form" data-method="patch" action="/api/file/trash_and_restore/<?= (int)$item['id'] ?>">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="restore">
                        <button type="submit">Restore</button>
                    </form>
                    <form method="post" class="api-form" data-method="patch" action="/api/file/trash_and_restore/<?= (int)$item['id'] ?>">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="purge">
                        <button type="submit">Purge</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>