<?php /** @var string|null $error */ /** @var array $data */ ?>
<main class="page narrow">
    <h1>Create account</h1>
    <p class="muted">SYS-01 — registration. New accounts are always operators.</p>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/register" class="card form">
        <label>Username
            <input type="text" name="username" required value="<?= htmlspecialchars((string)($data['username'] ?? '')) ?>">
        </label>
        <label>Email
            <input type="email" name="email" required value="<?= htmlspecialchars((string)($data['email'] ?? '')) ?>">
        </label>
        <label>Full name
            <input type="text" name="full_name" required value="<?= htmlspecialchars((string)($data['full_name'] ?? '')) ?>">
        </label>
        <label>Password (min 8 chars)
            <input type="password" name="password" required minlength="8">
        </label>
        <button type="submit" class="btn btn-primary">Create account</button>
    </form>
    <p class="muted">Already have one? <a href="/login">Sign in</a>.</p>
</main>