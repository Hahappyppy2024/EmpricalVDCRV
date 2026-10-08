<h1>Dashboard</h1>
<div id="flash" class="alert" style="display:none"></div>

<section class="grid">
    <div class="card">
        <h2>Mailbox at a glance</h2>
        <div id="mailbox-summary"><p class="muted">Loading&hellip;</p></div>
        <ul class="plain" id="recent-messages"></ul>
    </div>
    <div class="card">
        <h2>Recent account activity</h2>
        <ul class="plain" id="recent-access"></ul>
        <h2>Rules (enabled)</h2>
        <ul class="plain" id="recent-rules"></ul>
    </div>
</section>

<script>
    (function () {
        var P = window.P13;

        P.api('GET', '/api/mail/mailbox_overview').then(function (r) {
            var box = r.data && r.data.mailbox;
            if (!box) { return; }
            var total = 0, unread = 0;
            (box.folders || []).forEach(function (f) {
                total += f.total | 0;
                unread += f.unread | 0;
            });
            P.el('mailbox-summary').innerHTML =
                '<p><strong>' + unread + '</strong> unread of <strong>' + total + '</strong> messages across ' +
                box.folders.length + ' folders.</p>';
            P.renderList('recent-messages', (box.messages || []).slice(0, 6), function (m) {
                return '<li><a href="/message/' + m.id + '">' +
                    '<span class="' + (m.status === 'unread' ? 'badge unread' : 'badge') + '">' +
                    (m.status === 'unread' ? 'unread' : 'read') + '</span> ' +
                    P.esc(m.from_address) + ' &middot; ' + P.esc(m.subject) + '</a></li>';
            });
        });

        P.api('GET', '/api/mail/account_access?limit=6').then(function (r) {
            P.renderList('recent-access', (r.data.access || []), function (a) {
                return '<li><span class="badge ' + (a.status === 'success' ? 'ok' : 'err') + '">' +
                    P.esc(a.type) + '</span> ' + P.esc(a.message) + ' <span class="muted">' + P.esc(a.created_at) + '</span></li>';
            });
        });

        P.api('GET', '/api/mail/filters_and_rules').then(function (r) {
            var enabled = (r.data.rules && r.data.rules.items || []).filter(function (rule) {
                return rule.enabled === 1;
            });
            P.renderList('recent-rules', enabled, function (rule) {
                return '<li>' + P.esc(rule.name) + ' &rarr; ' + P.esc(rule.action_type) + ' ' + P.esc(rule.action_value) + '</li>';
            });
        });
    })();
</script>
