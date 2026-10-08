<?php /** Account access. Variables: $profile, $accessLog */ ?>
<div class="two-col">
    <section>
        <h2>Profile</h2>
        <form method="post" action="/account/update" class="form">
            <label>Username
                <input type="text" value="<?= e($profile['username']) ?>" disabled>
            </label>
            <label>Role
                <input type="text" value="<?= e($profile['role']) ?>" disabled>
            </label>
            <label>Full name
                <input type="text" name="full_name" value="<?= e($profile['full_name']) ?>">
            </label>
            <label>Email
                <input type="email" name="email" value="<?= e($profile['email']) ?>" required>
            </label>
            <button class="btn btn-primary" type="submit">Save profile</button>
        </form>
    </section>

    <section>
        <h2>Account access events</h2>
        <table class="table">
            <thead>
            <tr><th>Action</th><th>IP</th><th>When</th></tr>
            </thead>
            <tbody>
            <?php foreach ($accessLog as $entry): ?>
                <tr>
                    <td><span class="badge <?= badge($entry['action'] === 'login_failed' ? 'failed' : $entry['action']) ?>"><?= e($entry['action']) ?></span></td>
                    <td><?= e($entry['ip']) ?></td>
                    <td><?= e($entry['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
