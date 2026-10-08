<?php $page_title = 'Account access'; $page_slug = 'account'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>
<section class="card">
    <h2>Account access history</h2>
    <p class="meta">Registration, sign-in, sign-out and password-reset events for your account.</p>
    <table>
        <thead><tr><th>ID</th><th>Action</th><th>Status</th><th>IP</th><th>When</th></tr></thead>
        <tbody id="account-table">
            <tr><td colspan="5" class="meta">Loading…</td></tr>
        </tbody>
    </table>
</section>
<script>
window.CFS.account = {
    init: function () {
        CFS.api('/api/file/account_access').then(function (data) {
            var body = document.getElementById('account-table');
            if (!data.ok || !data.records.length) {
                body.innerHTML = '<tr><td colspan="5" class="meta">No account access events yet.</td></tr>';
                return;
            }
            body.innerHTML = data.records.map(function (r) {
                return '<tr><td>' + r.id + '</td><td>' + CFS.esc(r.action) + '</td>' +
                    '<td><span class="badge ' + (r.status === 'success' ? 'badge-green' : 'badge-red') + '">' + CFS.esc(r.status) + '</span></td>' +
                    '<td class="meta">' + CFS.esc(r.ip_address || '-') + '</td><td class="meta">' + CFS.esc(r.created_at) + '</td></tr>';
            }).join('');
        });
    }
};
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
