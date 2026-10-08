<h1>Account &amp; access</h1>
<div id="flash" class="alert" style="display:none"></div>

<div class="grid">
    <div class="card">
        <h2>Profile</h2>
        <dl class="meta">
            <dt>Username</dt><dd><?= e($user['username']) ?></dd>
            <dt>Email</dt><dd><?= e($user['email']) ?></dd>
            <dt>Role</dt><dd><?= e($user['role']) ?></dd>
            <dt>Status</dt><dd><?= e($user['status']) ?></dd>
        </dl>
        <h2>Change password</h2>
        <form id="password-form" class="stack">
            <input type="password" id="pw-current" placeholder="Current password" required>
            <input type="password" id="pw-new" placeholder="New password (min 8 chars)" required minlength="8">
            <button type="submit" class="btn">Update password</button>
        </form>
    </div>
    <div class="card">
        <h2>Access history</h2>
        <ul id="access-list" class="plain"></ul>
    </div>
</div>

<script>
    (function () {
        var P = window.P13;

        P.api('GET', '/api/mail/account_access').then(function (r) {
            P.renderList('access-list', r.data.access || [], function (a) {
                return '<li><span class="badge ' + (a.status === 'success' ? 'ok' : 'err') + '">' +
                    P.esc(a.type) + '</span> ' + P.esc(a.message) +
                    ' <span class="muted">' + P.esc(a.created_at) + ' &middot; ' + P.esc(a.ip_address) + '</span></li>';
            });
        });

        P.el('password-form').addEventListener('submit', function (ev) {
            ev.preventDefault();
            P.api('POST', '/api/mail/account_access', {
                action: 'change_password',
                current_password: P.el('pw-current').value,
                new_password: P.el('pw-new').value
            }).then(function (res) {
                P.flash((res.data && res.data.message) || (res.data && res.data.error) || 'Updated', res.ok ? 'ok' : 'error');
                P.el('pw-current').value = P.el('pw-new').value = '';
            });
        });
    })();
</script>
