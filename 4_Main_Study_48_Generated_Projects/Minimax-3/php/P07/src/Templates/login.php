<section class="card">
    <h2>Sign in</h2>
    <form method="post" action="/api/file/account_access" class="api-form" data-method="post">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label>Email <input type="email" name="email" required></label>
        <label>Password <input type="password" name="password" required></label>
        <button type="submit">Sign in</button>
    </form>
    <p><a href="/recover">Forgot password?</a> &middot; <a href="/register">Register</a></p>
</section>