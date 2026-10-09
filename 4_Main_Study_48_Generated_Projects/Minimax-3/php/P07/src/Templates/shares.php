<section class="card">
    <h2>Sharing links</h2>
    <table>
        <thead><tr><th>Token</th><th>Scope</th><th>Permission</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($shares as $share): ?>
            <tr>
                <td><code><?= htmlspecialchars($share['token_selector']) ?></code></td>
                <td><?= htmlspecialchars($share['scope']) ?></td>
                <td><?= htmlspecialchars($share['permission']) ?></td>
                <td><?= $share['revoked_at'] ? 'revoked' : 'active' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <h3>Incoming private shares</h3>
    <ul>
    <?php foreach ($incoming as $share): ?>
        <li>File #<?= (int)$share['file_id'] ?> (<?= htmlspecialchars($share['permission']) ?>)</li>
    <?php endforeach; ?>
    </ul>
    <p>Use the API endpoint <code>POST /api/file/sharing_links</code> with <code>target_type</code>, <code>target_id</code>, <code>scope</code>, and optional <code>permission</code>.</p>
</section>