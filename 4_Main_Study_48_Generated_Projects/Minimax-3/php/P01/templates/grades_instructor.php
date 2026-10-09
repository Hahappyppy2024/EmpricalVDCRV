<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1>Grades</h1>
    <ul class="grid">
        <?php foreach ($courses as $course): ?>
            <li class="card">
                <h2><?= $view->e($course['code']) ?> — <?= $view->e($course['title']) ?></h2>
                <p class="muted"><?= $view->e($course['category']) ?> · <?= $view->e($course['semester']) ?></p>
                <a class="btn btn-primary" href="/courses/<?= (int)$course['id'] ?>/grades/new">Enter grades</a>
                <a class="btn" href="/courses/<?= (int)$course['id'] ?>/export">Export</a>
                <a class="btn" href="/courses/<?= (int)$course['id'] ?>/roster">Roster</a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
