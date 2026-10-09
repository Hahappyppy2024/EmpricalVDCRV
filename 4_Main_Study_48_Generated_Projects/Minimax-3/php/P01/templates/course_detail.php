<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <header class="page-header">
        <span class="badge"><?= $view->e($course['category']) ?></span>
        <span class="badge"><?= $view->e($course['semester']) ?></span>
        <h1><?= $view->e($course['code']) ?> — <?= $view->e($course['title']) ?></h1>
        <p class="muted">Instructor: <?= $view->e($course['instructor_name']) ?> · Visibility: <?= $view->e($course['visibility']) ?></p>
    </header>
    <article class="card">
        <h2>About this course</h2>
        <p><?= $view->e($course['description']) ?></p>
        <?php if (!empty($user) && $user['role'] === 'student' && $course['visibility'] !== 'hidden'): ?>
            <a class="btn btn-primary" href="/enroll/<?= (int)$course['id'] ?>">Enroll</a>
        <?php endif; ?>
        <?php if (!empty($user) && in_array($user['role'], ['instructor', 'admin'], true)): ?>
            <a class="btn" href="/courses/<?= (int)$course['id'] ?>/materials">Materials</a>
            <a class="btn" href="/courses/<?= (int)$course['id'] ?>/announcements">Announcements</a>
            <a class="btn" href="/courses/<?= (int)$course['id'] ?>/discussion">Discussion</a>
            <a class="btn" href="/courses/<?= (int)$course['id'] ?>/assignments">Assignments</a>
            <a class="btn" href="/courses/<?= (int)$course['id'] ?>/quizzes">Quizzes</a>
            <a class="btn" href="/courses/<?= (int)$course['id'] ?>/roster">Roster</a>
            <a class="btn" href="/courses/<?= (int)$course['id'] ?>/export">Export grades</a>
        <?php endif; ?>
    </article>
</section>
