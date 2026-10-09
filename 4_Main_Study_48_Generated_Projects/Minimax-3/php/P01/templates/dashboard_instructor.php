<?php /** @var \LMS\Http\View $view */ ?>
<section class="dashboard">
    <header class="welcome">
        <h1>Instructor dashboard</h1>
        <p class="muted">Welcome back, <?= $view->e($user['full_name']) ?>.</p>
    </header>
    <div class="grid">
        <a class="card" href="/courses">All courses</a>
        <a class="card" href="/my/courses">My courses</a>
        <a class="card" href="/grades">Gradebook</a>
        <a class="card" href="/exports">My exports</a>
        <a class="card" href="/frontend-api">Frontend API</a>
    </div>
</section>
