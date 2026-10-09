<section class="card">
    <h2>Files</h2>
    <table>
        <thead><tr><th>Name</th><th>Size</th><th>Owner</th><th>Team</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($files as $file): ?>
            <tr>
                <td><a href="/files/<?= (int)$file['id'] ?>"><?= htmlspecialchars($file['original_name']) ?></a></td>
                <td><?= (int)$file['size'] ?></td>
                <td><?= htmlspecialchars((string)$file['owner_id']) ?></td>
                <td><?= htmlspecialchars((string)$file['team_id']) ?></td>
                <td>
                    <a href="/files/<?= (int)$file['id'] ?>/download">Download</a>
                    <a href="/files/<?= (int)$file['id'] ?>/preview">Preview</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>