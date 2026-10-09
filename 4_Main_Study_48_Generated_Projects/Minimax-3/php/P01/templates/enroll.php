<?php /** @var \LMS\Http\View $view */ ?>
<section class="page">
    <h1>Enroll in <?= $view->e($course['code']) ?> — <?= $view->e($course['title']) ?></h1>
    <?php if (!empty($already)): ?>
        <p class="banner">You are already enrolled. <a href="/my/courses">View my courses</a>.</p>
    <?php else: ?>
        <form method="post" action="/enroll/<?= (int)$course['id'] ?>" class="card">
            <input type="hidden" name="_csrf" value="<?= $view->e($csrf ?? '') ?>">
            <p>Confirm enrollment in <strong><?= $view->e($course['code']) ?></strong>?</p>
            <button class="btn btn-primary" type="submit">Confirm enrollment</button>
        </form>
    <?php endif; ?>
</section>
