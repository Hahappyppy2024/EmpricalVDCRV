<?php /** @var \LMS\Http\View $view */ ?>
<section class="card narrow">
    <h1>Error <?= (int)$status ?></h1>
    <p class="muted"><?= $view->e($error ?? 'Unexpected error.') ?></p>
    <p><a class="btn btn-primary" href="/">Return home</a> <a class="btn" href="/dashboard">Go to dashboard</a></p>
</section>
