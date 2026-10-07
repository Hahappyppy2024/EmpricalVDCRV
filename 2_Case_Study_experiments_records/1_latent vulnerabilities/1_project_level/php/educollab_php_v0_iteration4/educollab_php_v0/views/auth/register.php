<div class="card">
    <h2>Register (Student)</h2>
    <form method="post" action="/register">
        <?= csrf_input() ?>
        <label>Username</label>
        <input name="username" />
        <label>Display name</label>
        <input name="display_name" />
        <label>Password</label>
        <input name="password" type="password" />
        <div style="margin-top:12px"><button type="submit">Create account</button></div>
    </form>
</div>