<div class="card">
    <h2>Uploads (course <?= h($course_id) ?>)</h2>
    <a href="/courses/<?= h($course_id) ?>/uploads/new">Upload file (staff)</a>
    <table>
        <thead><tr><th>upload_id</th><th>name</th><th>by</th><th>created</th><th>actions</th></tr></thead>
        <tbody>
        <?php foreach ($uploads as $u): ?>
            <tr>
                <td><?= h((string)$u['upload_id']) ?></td>
                <td><?= h($u['original_name']) ?></td>
                <td><?= h($u['uploader_username'] ?? '') ?></td>
                <td><?= h($u['created_at']) ?></td>
                <td>
                    <a href="/courses/<?= h($course_id) ?>/uploads/<?= h($u['upload_id']) ?>">Download</a>
                    <form style="display:inline" method="post" action="/courses/<?= h($course_id) ?>/uploads/<?= h($u['upload_id']) ?>/delete">
                        <?= csrf_input() ?>
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>