<?php $page_title = 'Storage quota'; $page_slug = 'quota'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>
<div class="grid">
    <div class="card">
        <h2>My storage quota</h2>
        <div id="quota-mine"><p class="meta">Loading…</p></div>
        <button class="btn btn-ghost btn-sm" id="quota-recalc">Recalculate usage</button>
    </div>
    <div class="card" id="quota-admin" hidden>
        <h2>All users (admin)</h2>
        <table>
            <thead><tr><th>User</th><th>Quota</th><th>Used</th><th>%</th></tr></thead>
            <tbody id="quota-table"></tbody>
        </table>
    </div>
</div>
<script>
window.CFS.quota = {
    init: function () {
        var self = this;
        var refresh = function () {
            CFS.api('/api/file/storage_quota').then(function (data) {
                if (!data.ok) { return; }
                var q = data.quota;
                var mine = document.getElementById('quota-mine');
                mine.innerHTML = '<p><strong>Quota:</strong> ' + CFS.formatBytes(q.quota_bytes) +
                    ' · <strong>Used:</strong> ' + CFS.formatBytes(q.used_bytes) +
                    ' · <strong>Free:</strong> ' + CFS.formatBytes(q.remaining_bytes) +
                    ' (' + q.percent + '%)</p>' +
                    '<div class="progress"><div class="progress-bar" style="width:' + Math.min(q.percent, 100) + '%"></div></div>';
                if (data.quota.all_users) {
                    document.getElementById('quota-admin').hidden = false;
                    var body = document.getElementById('quota-table');
                    body.innerHTML = data.quota.all_users.map(function (u) {
                        var pct = u.quota_bytes > 0 ? ((u.used_bytes / u.quota_bytes) * 100).toFixed(1) : 0;
                        return '<tr><td>' + CFS.esc(u.username) + '</td><td>' + CFS.formatBytes(u.quota_bytes) + '</td>' +
                            '<td>' + CFS.formatBytes(u.used_bytes) + '</td><td>' + pct + '%</td></tr>';
                    }).join('');
                }
            });
        };
        document.getElementById('quota-recalc').addEventListener('click', function () {
            CFS.api('/api/file/storage_quota', { method: 'POST', body: {} }).then(function (data) {
                CFS.toast(data.ok ? data.message : 'Failed', !data.ok);
                refresh();
            });
        });
        refresh();
    }
};
</script>
<style>.progress{background:#e6edf5;border-radius:8px;height:12px;overflow:hidden;margin-top:.5rem}.progress-bar{background:#2f6fed;height:100%}</style>
<?php require __DIR__ . '/partials/footer.php'; ?>
