<h1>Register</h1>
<?php if (!empty($error)): ?>
    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<form method="post" action="/register">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <label>Username</label>
    <input type="text" name="username" required>
    <label>Password</label>
    <input type="password" name="password" required>
    <label>Display Name</label>
    <input type="text" name="display_name" required>
    <button type="submit">Create account</button>
</form>