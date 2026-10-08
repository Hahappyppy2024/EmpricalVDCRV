<?php $page_title = 'Search'; $page_slug = 'search'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>
<div class="card">
    <h2>Search files</h2>
    <form id="search-form" class="searchbar">
        <input type="text" name="q" placeholder="Search by name, description…">
        <button class="btn" type="submit">Search</button>
    </form>
    <div class="toolbar" style="margin-top:.75rem">
        <input type="text" id="f-tag" placeholder="Tag filter" style="max-width:160px">
        <input type="text" id="f-owner" placeholder="Owner" style="max-width:160px">
        <input type="date" id="f-from" title="From date">
        <input type="date" id="f-to" title="To date">
        <button class="btn btn-ghost btn-sm" id="f-reset" type="button">Reset</button>
    </div>
    <p class="meta" id="search-count"></p>
</div>
<div class="card">
    <table>
        <thead><tr><th>Name</th><th>Owner</th><th>Size</th><th>Tags</th><th>Created</th><th>Action</th></tr></thead>
        <tbody id="search-table"><tr><td colspan="6" class="meta">Run a search to see results.</td></tr></tbody>
    </table>
</div>
<script>
window.CFS.search = {
    init: function () {
        var self = this;
        var form = document.getElementById('search-form');
        var run = function (e) {
            if (e) { e.preventDefault(); }
            var params = new URLSearchParams({ q: form.q.value });
            ['f-tag', 'f-owner', 'f-from', 'f-to'].forEach(function (id) {
                var v = document.getElementById(id).value;
                if (v) { params.append(id.replace('f-', ''), v); }
            });
            CFS.api('/api/file/search?' + params.toString()).then(function (data) {
                var body = document.getElementById('search-table');
                document.getElementById('search-count').textContent = data.ok ? (data.count + ' result(s)') : '';
                if (!data.ok || !data.records.length) {
                    body.innerHTML = '<tr><td colspan="6" class="meta">No matching records.</td></tr>';
                    return;
                }
                body.innerHTML = data.records.map(function (r) {
                    var ownerMine = CFS.esc(r.owner) === CFS.esc(window.CFS_USER || '');
                    var action = ownerMine ? '<a class="btn btn-ghost btn-sm" href="/files/' + r.id + '/download">Download</a>' : '<span class="badge badge-blue">shared</span>';
                    return '<tr><td>' + CFS.esc(r.name) + '</td><td class="meta">' + CFS.esc(r.owner) + '</td>' +
                        '<td class="meta">' + CFS.formatBytes(r.size_bytes) + '</td><td class="meta">' + CFS.esc(r.tags) + '</td>' +
                        '<td class="meta">' + CFS.esc(r.created_at) + '</td><td>' + action + '</td></tr>';
                }).join('');
            });
        };
        form.addEventListener('submit', run);
        document.getElementById('f-reset').addEventListener('click', function () {
            ['q', 'f-tag', 'f-owner', 'f-from', 'f-to'].forEach(function (id) {
                var el = id === 'q' ? form.q : document.getElementById(id);
                el.value = '';
            });
            run(null);
        });
    }
};
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
