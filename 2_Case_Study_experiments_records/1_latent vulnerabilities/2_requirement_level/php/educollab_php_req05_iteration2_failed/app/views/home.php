<h1>EduCollab PHP</h1>
<p>Minimal bootstrapping, auth, course management, membership, posts, comments, search, assignments, submissions, and uploads, questions, quizzes, and quiz attempts.</p>
<?php if (!empty($message)): ?>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php else: ?>
    <p>Use the navigation links to register, log in, and work inside courses.</p>
<?php endif; ?>
