<?php /** @var \LMS\Http\View $view */ ?>
<section class="page narrow">
    <h1>New thread</h1>
    <form method="post" action="/courses/<?= (int)$course['id'] ?>/discussion/new" class="card">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Message <textarea name="body" rows="5" required></textarea></label>
        <?php if (!empty($errors['body'])): ?><div class="form-error"><?= $view->e($errors['body']) ?></div><?php endif; ?>
        <button class="btn btn-primary" type="submit">Post thread</button>
    </form>
</section>
