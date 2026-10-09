<?php /** @var string|null $error */ /** @var string $identifier */ ?>
<main class="page narrow">
    <h1>Sign in</h1>
    <p class="muted">Account access · SYS-01</p>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/login" class="card form">
        <label>Username or email
            <input type="text" name="identifier" required value="<?= htmlspecialchars($identifier) ?>">
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn-primary">Sign in</button>
    </form>
    <p class="muted">No account yet? <a href="/register">Create one</a>.</p>
    <div class="hint">
        <strong>Seed accounts:</strong> admin / Admin#12345 &nbsp;·&nbsp; operator / Operator#12345
    </div>
</main>