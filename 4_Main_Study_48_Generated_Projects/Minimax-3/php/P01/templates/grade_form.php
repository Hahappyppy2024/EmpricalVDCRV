<?php /** @var \LMS\Http\View $view */ ?>
<section class="page narrow">
    <h1>Enter grade — <?= $view->e($course['code']) ?></h1>
    <form method="post" action="/courses/<?= (int)$course['id'] ?>/grades/new" class="card">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Student
            <select name="student_id" required>
                <option value="">Select…</option>
                <?php foreach ($roster as $r): ?>
                    <option value="<?= (int)$r['user_id'] ?>" <?= (int)$input['student_id'] === (int)$r['user_id'] ? 'selected' : '' ?>>
                        <?= $view->e($r['full_name']) ?> (<?= $view->e($r['username']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['student_id'])): ?><div class="form-error"><?= $view->e($errors['student_id']) ?></div><?php endif; ?>
        </label>
        <label>Item label <input name="item_label" value="<?= $view->e($input['item_label']) ?>" required></label>
        <?php if (!empty($errors['item_label'])): ?><div class="form-error"><?= $view->e($errors['item_label']) ?></div><?php endif; ?>
        <label>Score <input type="number" step="0.1" name="score" value="<?= $view->e((string)$input['score']) ?>" required></label>
        <?php if (!empty($errors['score'])): ?><div class="form-error"><?= $view->e($errors['score']) ?></div><?php endif; ?>
        <label>Max score <input type="number" step="0.1" name="max_score" value="<?= $view->e((string)$input['max_score']) ?>"></label>
        <label>Feedback <textarea name="feedback"><?= $view->e($input['feedback']) ?></textarea></label>
        <button class="btn btn-primary" type="submit">Record grade</button>
    </form>
</section>
