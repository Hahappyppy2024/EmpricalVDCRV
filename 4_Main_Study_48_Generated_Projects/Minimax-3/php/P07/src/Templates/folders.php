<section class="card">
    <h2>Folder management</h2>
    <table>
        <thead><tr><th>Name</th><th>Owner</th><th>Team</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($folders as $folder): ?>
            <tr>
                <td><?= htmlspecialchars($folder['name']) ?></td>
                <td><?= htmlspecialchars((string)$folder['owner_id']) ?></td>
                <td><?= htmlspecialchars((string)$folder['team_id']) ?></td>
                <td>
                    <form method="post" class="api-form" data-method="patch" action="/api/file/folder_management/<?= (int)$folder['id'] ?>">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="rename">
                        <input type="text" name="name" value="<?= htmlspecialchars($folder['name']) ?>" required>
                        <button type="submit">Rename</button>
                    </form>
                    <form method="post" class="api-form" data-method="patch" action="/api/file/folder_management/<?= (int)$folder['id'] ?>">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <h3>Create folder</h3>
    <form method="post" action="/api/file/folder_management" class="api-form" data-method="post" data-redirect="/folders">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="text" name="name" required placeholder="Folder name">
        <select name="team_id">
            <option value="">Personal</option>
            <?php foreach ($teams as $team): ?>
                <option value="<?= (int)$team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Create</button>
    </form>
</section>