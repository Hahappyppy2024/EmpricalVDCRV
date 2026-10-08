<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'P13 Mail Server / Admin Console') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/dashboard">P13 Mail Console</a>
    <?php if ($user !== null): ?>
    <nav class="nav">
        <a href="/dashboard" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="/mailbox" class="<?= $active === 'mailbox' ? 'active' : '' ?>">Mailbox</a>
        <a href="/compose" class="<?= $active === 'compose' ? 'active' : '' ?>">Compose</a>
        <a href="/attachments" class="<?= $active === 'attachments' ? 'active' : '' ?>">Attachments</a>
        <a href="/contacts" class="<?= $active === 'contacts' ? 'active' : '' ?>">Contacts</a>
        <a href="/rules" class="<?= $active === 'rules' ? 'active' : '' ?>">Rules</a>
        <a href="/import-export" class="<?= $active === 'import_export' ? 'active' : '' ?>">Import/Export</a>
        <a href="/errors" class="<?= $active === 'errors' ? 'active' : '' ?>">API Errors</a>
        <a href="/account" class="<?= $active === 'account' ? 'active' : '' ?>">Account</a>
        <?php if (in_array($user['role'], ['domain_admin', 'system_admin'], true)): ?>
            <a href="/domains" class="<?= $active === 'domains' ? 'active' : '' ?>">Domains</a>
            <a href="/quarantine" class="<?= $active === 'quarantine' ? 'active' : '' ?>">Quarantine</a>
        <?php endif; ?>
        <?php if ($user['role'] === 'system_admin'): ?>
            <a href="/audit" class="<?= $active === 'audit' ? 'active' : '' ?>">Audit logs</a>
        <?php endif; ?>
    </nav>
    <div class="userbox">
        <span class="role-pill role-<?= e($user['role']) ?>"><?= e($user['role']) ?></span>
        <span class="uname"><?= e($user['display_name'] ?: $user['username']) ?></span>
        <form method="post" action="/logout" class="inline">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button type="submit" class="btn btn-small">Sign out</button>
        </form>
    </div>
    <?php endif; ?>
</header>
<main class="main">
    <?= $content ?>
</main>
<footer class="footer">
    P13 Mail Server / Admin Console &middot; synthetic benchmark (Roundcube-aligned)
</footer>
<script>
    window.P13 = {
        csrf: <?= json_encode($csrf ?? '') ?>,
        wsUrl: <?= json_encode($wsUrl ?? '') ?>,
        user: <?= json_encode($user !== null ? [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
        ] : null) ?>
    };
</script>
<script src="/assets/js/api.js"></script>
</body>
</html>
