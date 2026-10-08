<h1>Quarantine</h1>
<div id="flash" class="alert" style="display:none"></div>

<div class="card">
    <form id="filter-form" class="stack row">
        <select id="q-status">
            <option value="">All statuses</option>
            <option value="quarantined">Quarantined</option>
            <option value="released">Released</option>
            <option value="deleted">Deleted</option>
        </select>
        <input type="text" id="q-query" placeholder="Search sender / recipient / subject">
        <button type="submit" class="btn btn-small">Filter</button>
    </form>
</div>

<div class="card">
    <ul id="quarantine-list" class="plain"></ul>
</div>

<script>
    (function () {
        var P = window.P13;

        function load() {
            var qs = [];
            if (P.el('q-status').value) { qs.push('status=' + P.el('q-status').value); }
            if (P.el('q-query').value.trim()) { qs.push('q=' + encodeURIComponent(P.el('q-query').value.trim())); }
            P.api('GET', '/api/mail/quarantine' + (qs.length ? '?' + qs.join('&') : '')).then(function (r) {
                var items = (r.data.quarantine && r.data.quarantine.items) || [];
                P.renderList('quarantine-list', items, function (item) {
                    var actions = '';
                    if (item.status === 'quarantined') {
                        actions = ' <button class="btn btn-small" data-action="release" data-id="' + item.id + '">Release</button>' +
                            ' <button class="btn btn-small btn-danger" data-action="delete" data-id="' + item.id + '">Delete</button>';
                    }
                    return '<li>' +
                        '<span class="badge ' + (item.status === 'quarantined' ? 'warn' : item.status === 'released' ? 'ok' : 'err') + '">' + P.esc(item.status) + '</span> ' +
                        '<strong>' + P.esc(item.subject) + '</strong>' +
                        '<div class="muted">' + P.esc(item.from_address) + ' &rarr; ' + P.esc(item.to_address) +
                        ' &middot; ' + P.esc(item.domain_name) + ' &middot; score ' + item.score + '</div>' +
                        '<div class="muted">' + P.esc(item.reason) + '</div>' +
                        actions +
                        '</li>';
                });
                document.querySelectorAll('[data-action]').forEach(function (b) {
                    b.addEventListener('click', function () {
                        P.api('POST', '/api/mail/quarantine', {
                            id: parseInt(b.getAttribute('data-id'), 10),
                            action: b.getAttribute('data-action')
                        }).then(function (res) {
                            P.flash((res.data && res.data.message) || 'Done', res.ok ? 'ok' : 'error');
                            load();
                        });
                    });
                });
            });
        }

        P.el('filter-form').addEventListener('submit', function (ev) {
            ev.preventDefault();
            load();
        });

        load();
    })();
</script>
