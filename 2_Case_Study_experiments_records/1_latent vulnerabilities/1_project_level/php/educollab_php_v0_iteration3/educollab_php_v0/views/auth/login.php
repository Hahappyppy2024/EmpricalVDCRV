<div class="card">
    <h2>Login</h2>
    <form method="post" action="/login">
        <?= csrf_input() ?>
        <label>Username</label>
        <input name="username" />
        <label>Password</label>
        <input name="password" type="password" />
        <button type="submit">Login</button>
    </form>

</div>