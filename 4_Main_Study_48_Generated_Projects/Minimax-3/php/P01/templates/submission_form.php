<?php /** @var \LMS\Http\View $view */ ?>
<section class="page narrow">
    <h1>Submit: <?= $view->e($assignment['title']) ?></h1>
    <p class="muted">Due <?= $view->e($view->formatDate($assignment['due_at'])) ?>.</p>
    <?php if (!empty($existing)): ?>
        <p class="banner">You already submitted this on <?= $view->e($view->formatDate($existing['submitted_at'])) ?>.</p>
    <?php else: ?>
        <form method="post" action="/assignments/<?= (int)$assignment['id'] ?>/submit" enctype="multipart/form-data" class="card">
            <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
            <label>Comment <textarea name="comment" rows="3"></textarea></label>
            <label>File <input type="file" name="file" required></label>
            <button class="btn btn-primary" type="submit">Submit</button>
        </form>
    <?php endif; ?>
</section>
