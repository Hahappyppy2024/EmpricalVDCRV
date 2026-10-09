<?php /** @var \LMS\Http\View $view */ ?>
<section class="page narrow">
    <h1>Edit post</h1>
    <form method="post" action="/discussion/<?= (int)$post['id'] ?>/edit" class="card">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Body <textarea name="body" rows="5" required><?= $view->e($post['body']) ?></textarea></label>
        <?php if (!empty($errors['body'])): ?><div class="form-error"><?= $view->e($errors['body']) ?></div><?php endif; ?>
        <button class="btn btn-primary" type="submit">Update</button>
    </form>
</section>
