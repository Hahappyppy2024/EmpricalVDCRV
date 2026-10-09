<?php /** @var \LMS\Http\View $view */ ?>
<section class="page narrow">
    <h1>Upload material</h1>
    <form method="post" action="/courses/<?= (int)$course['id'] ?>/materials/new" enctype="multipart/form-data" class="card">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Title <input name="title" required></label>
        <?php if (!empty($errors['title'])): ?><div class="form-error"><?= $view->e($errors['title']) ?></div><?php endif; ?>
        <label>Description <textarea name="description"></textarea></label>
        <label>File <input type="file" name="file" required></label>
        <?php if (!empty($errors['file'])): ?><div class="form-error"><?= $view->e($errors['file']) ?></div><?php endif; ?>
        <button class="btn btn-primary" type="submit">Upload</button>
    </form>
</section>
