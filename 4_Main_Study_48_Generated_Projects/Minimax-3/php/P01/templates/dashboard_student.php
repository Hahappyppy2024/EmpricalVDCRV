<?php /** @var \LMS\Http\View $view */ ?>
<section class="dashboard">
    <header class="welcome">
        <h1>Hi, <?= $view->e($user['full_name']) ?> 👋</h1>
        <p class="muted">Role: <?= $view->e($user['role']) ?></p>
    </header>
    <div class="grid">
        <a class="card" href="/courses">Discover courses</a>
        <a class="card" href="/my/courses">My enrollments</a>
        <a class="card" href="/grades">My grades</a>
        <a class="card" href="/frontend-api">Frontend API states</a>
    </div>
</section>
