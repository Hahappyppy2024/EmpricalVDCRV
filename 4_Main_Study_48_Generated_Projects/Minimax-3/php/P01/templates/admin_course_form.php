<?php /** @var \LMS\Http\View $view */ ?>
<section class="page narrow">
    <h1>Create course</h1>
    <form method="post" action="/admin/courses/new" class="card">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Code <input name="code" value="<?= $view->e($input['code']) ?>" placeholder="CS101" required>
            <?php if (!empty($errors['code'])): ?><div class="form-error"><?= $view->e($errors['code']) ?></div><?php endif; ?>
        </label>
        <label>Title <input name="title" value="<?= $view->e($input['title']) ?>" required>
            <?php if (!empty($errors['title'])): ?><div class="form-error"><?= $view->e($errors['title']) ?></div><?php endif; ?>
        </label>
        <label>Description <textarea name="description"><?= $view->e($input['description']) ?></textarea></label>
        <label>Category <input name="category" value="<?= $view->e($input['category']) ?>"></label>
        <label>Semester <input name="semester" value="<?= $view->e($input['semester']) ?>"></label>
        <label>Instructor
            <select name="instructor_id" required>
                <option value="">Select…</option>
                <?php foreach ($instructors as $ins): ?>
                    <option value="<?= (int)$ins['id'] ?>" <?= (int)$input['instructor_id'] === (int)$ins['id'] ? 'selected' : '' ?>><?= $view->e($ins['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['instructor_id'])): ?><div class="form-error"><?= $view->e($errors['instructor_id']) ?></div><?php endif; ?>
        </label>
        <label>Visibility
            <select name="visibility">
                <?php foreach (['open','closed','hidden'] as $v): ?>
                    <option value="<?= $v ?>" <?= $input['visibility'] === $v ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Create</button>
    </form>
</section>
