<?php /** @var \LMS\Http\View $view */ ?>
<section class="page narrow">
    <h1>New assignment</h1>
    <form method="post" action="/courses/<?= (int)$course['id'] ?>/assignments/new" class="card">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Title <input name="title" value="<?= $view->e($input['title']) ?>" required></label>
        <?php if (!empty($errors['title'])): ?><div class="form-error"><?= $view->e($errors['title']) ?></div><?php endif; ?>
        <label>Instructions <textarea name="instructions" rows="5"><?= $view->e($input['instructions']) ?></textarea></label>
        <label>Due at <input type="datetime-local" name="due_at" value="<?= $view->e($input['due_at']) ?>" required></label>
        <?php if (!empty($errors['due_at'])): ?><div class="form-error"><?= $view->e($errors['due_at']) ?></div><?php endif; ?>
        <label>Max score <input type="number" step="0.1" name="max_score" value="<?= $view->e((string)$input['max_score']) ?>"></label>
        <button class="btn btn-primary" type="submit">Create</button>
    </form>
</section>
