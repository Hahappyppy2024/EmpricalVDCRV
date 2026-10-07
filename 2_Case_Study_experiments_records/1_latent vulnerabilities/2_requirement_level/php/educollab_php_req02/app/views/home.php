<h1>EduCollab PHP</h1>
<p>Minimal bootstrapping, auth, course management, membership, and course-scoped role enforcement.</p>
<?php if (!empty($message)): ?>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
    <p>Use the navigation links to register, log in, manage courses, and manage course members.</p>
<?php endif; ?>
