<h1>Course Members</h1>
<?php if ($course): ?>
    <p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if (!empty($can_manage)): ?>
    <p><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/members/new">Add member</a></p>
<?php endif; ?>
<?php if (!$members): ?>
    <p>No members found.</p>
<?php endif; ?>
<?php foreach ($members as $member): ?>
    <div class="card">
        <p><strong>Membership ID:</strong> <?= htmlspecialchars((string) $member['membership_id'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>User:</strong> <?= htmlspecialchars($member['display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($member['username'], ENT_QUOTES, 'UTF-8') ?>)</p>
        <p><strong>Role:</strong> <?= htmlspecialchars($member['role_in_course'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Created At:</strong> <?= htmlspecialchars($member['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php if (!empty($can_manage)): ?>
            <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/members/<?= htmlspecialchars((string) $member['membership_id'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <label>Update role</label>
                <select name="role_in_course">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>" <?= $role === $member['role_in_course'] ? 'selected' : '' ?>><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Save role</button>
            </form>
            <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/members/<?= htmlspecialchars((string) $member['membership_id'], ENT_QUOTES, 'UTF-8') ?>/delete">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Remove member</button>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>