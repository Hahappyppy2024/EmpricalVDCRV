<?php /** @var \LMS\Http\View $view */ ?>
<section class="page narrow">
    <h1>Post announcement</h1>
    <form method="post" action="/courses/<?= (int)$course['id'] ?>/announcements/new" class="card">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Title <input name="title" value="<?= $view->e($input['title']) ?>" required></label>
        <?php if (!empty($errors['title'])): ?><div class="form-error"><?= $view->e($errors['title']) ?></div><?php endif; ?>
        <label>Body <textarea name="body" rows="6" required><?= $view->e($input['body']) ?></textarea></label>
        <?php if (!empty($errors['body'])): ?><div class="form-error"><?= $view->e($errors['body']) ?></div><?php endif; ?>
        <button class="btn btn-primary" type="submit">Post</button>
    </form>
</section>
