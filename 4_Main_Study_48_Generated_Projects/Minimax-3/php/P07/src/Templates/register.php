<section class="card">
    <h2>Register</h2>
    <form method="post" action="/api/file/account_access" class="api-form" data-method="post" data-redirect="/dashboard">
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label>Name <input type="text" name="name" required></label>
        <label>Email <input type="email" name="email" required></label>
        <label>Password <input type="password" name="password" required minlength="8"></label>
        <button type="submit">Create account</button>
    </form>
</section>