<h1>My Memberships</h1>
<?php if (!$memberships): ?>
    <p>No memberships yet.</p>
<?php endif; ?>
<?php foreach ($memberships as $membership): ?>
    <div class="card">
        <p><strong>Membership ID:</strong> <?= htmlspecialchars((string) $membership['membership_id'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Course:</strong> <a href="/courses/<?= htmlspecialchars((string) $membership['course_id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($membership['course_title'], ENT_QUOTES, 'UTF-8') ?></a></p>
        <p><strong>Role:</strong> <?= htmlspecialchars($membership['role_in_course'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Created At:</strong> <?= htmlspecialchars($membership['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
<?php endforeach; ?>
