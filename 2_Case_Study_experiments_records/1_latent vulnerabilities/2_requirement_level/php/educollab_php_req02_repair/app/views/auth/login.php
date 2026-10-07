<h1>Login</h1>
<?php if (!empty($error)): ?>
    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<form method="post" action="/login">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <label>Username</label>
    <input type="text" name="username" required>
    <label>Password</label>
    <input type="password" name="password" required>
    <button type="submit">Login</button>
</form>