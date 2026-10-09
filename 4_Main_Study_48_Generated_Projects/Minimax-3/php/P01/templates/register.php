<?php /** @var \LMS\Http\View $view */ ?>
<section class="card narrow">
    <h1>Register</h1>
    <form method="post" action="/register">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Username
            <input name="username" value="<?= $view->e($input['username']) ?>" required>
            <?php if (!empty($errors['username'])): ?><div class="form-error"><?= $view->e($errors['username']) ?></div><?php endif; ?>
        </label>
        <label>Email
            <input type="email" name="email" value="<?= $view->e($input['email']) ?>" required>
            <?php if (!empty($errors['email'])): ?><div class="form-error"><?= $view->e($errors['email']) ?></div><?php endif; ?>
        </label>
        <label>Full name
            <input name="full_name" value="<?= $view->e($input['full_name']) ?>" required>
            <?php if (!empty($errors['full_name'])): ?><div class="form-error"><?= $view->e($errors['full_name']) ?></div><?php endif; ?>
        </label>
        <label>Role
            <select name="role">
                <option value="student" <?= $input['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                <option value="instructor" <?= $input['role'] === 'instructor' ? 'selected' : '' ?>>Instructor</option>
            </select>
        </label>
        <label>Password (min 8 chars)
            <input type="password" name="password" required>
            <?php if (!empty($errors['password'])): ?><div class="form-error"><?= $view->e($errors['password']) ?></div><?php endif; ?>
        </label>
        <?php if (!empty($errors['form'])): ?>
            <div class="form-error"><?= $view->e($errors['form']) ?></div>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Create account</button>
    </form>
    <p class="muted">Already have an account? <a href="/login">Sign in</a></p>
</section>
