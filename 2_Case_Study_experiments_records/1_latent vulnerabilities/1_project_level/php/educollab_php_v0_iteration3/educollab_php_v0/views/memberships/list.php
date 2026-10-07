<div class="card">
    <h2>Members</h2>
    <p><a href="/courses/<?= h($course_id) ?>/members/new">Add member</a></p>

    <table>
        <thead>
        <tr>
            <th>User</th>
            <th>Role</th>
            <th>Remove</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($memberships as $m): ?>
            <tr>
                <td><?= h($m['username']) ?> (<?= h($m['display_name']) ?>)</td>
                <td>
                    <form method="post" action="/courses/<?= h($course_id) ?>/members/<?= h($m['membership_id']) ?>">
                        <?= csrf_input() ?>
                        <select name="role_in_course">
                            <?php foreach (['admin','teacher','student','assistant','senior-assistant'] as $r): ?>
                                <option value="<?= h($r) ?>" <?= $m['role_in_course']===$r?'selected':'' ?>><?= h($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Update</button>
                    </form>
                </td>
                <td>
                    <form method="post" action="/courses/<?= h($course_id) ?>/members/<?= h($m['membership_id']) ?>/delete">
                        <?= csrf_input() ?>
                        <button type="submit">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>