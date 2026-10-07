<h1>Add Member</h1>
<?php if ($course): ?>
    <p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/members">
    <label>User</label>
    <select name="user_id" required>
        <option value="">Select a user</option>
        <?php foreach ($users as $user): ?>
            <option value="<?= htmlspecialchars((string) $user['user_id'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <label>Role in course</label>
    <select name="role_in_course" required>
        <?php foreach ($roles as $role): ?>
            <option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Add member</button>
</form>
