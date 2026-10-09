<section class="card">
    <h2>Team spaces</h2>
    <table>
        <thead><tr><th>Name</th><th>Quota</th><th>Used</th></tr></thead>
        <tbody>
        <?php foreach ($teams as $team): ?>
            <tr>
                <td><?= htmlspecialchars($team['name']) ?></td>
                <td><?= (int)$team['quota_limit'] ?></td>
                <td><?= (int)$team['used_bytes'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p>Use the API endpoint <code>POST /api/file/team_spaces</code> with <code>name</code> and <code>quota_limit</code>.</p>
</section>