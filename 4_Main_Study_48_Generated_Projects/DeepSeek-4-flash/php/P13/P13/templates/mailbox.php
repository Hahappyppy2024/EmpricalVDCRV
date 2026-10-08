<h1>Mailbox overview</h1>
<div id="flash" class="alert" style="display:none"></div>

<div class="mailbox-layout">
    <aside class="card folders">
        <h2>Folders</h2>
        <ul id="folder-list" class="plain"></ul>
        <form id="folder-form" class="stack">
            <input type="text" id="new-folder" placeholder="New folder name" maxlength="64">
            <button type="submit" class="btn btn-small">Create folder</button>
        </form>
    </aside>
    <section class="card messages">
        <h2 id="folder-title">Messages</h2>
        <form id="search-form" class="stack row">
            <input type="text" id="search-q" placeholder="Search subject / body / sender">
            <button type="submit" class="btn btn-small">Search</button>
        </form>
        <ul id="message-list" class="plain"></ul>
    </section>
</div>

<script>
    (function () {
        var P = window.P13;
        var currentFolder = null;
        var currentQ = '';

        function load() {
            var qs = [];
            if (currentFolder) { qs.push('folder_id=' + currentFolder); }
            if (currentQ) { qs.push('q=' + encodeURIComponent(currentQ)); }
            P.api('GET', '/api/mail/mailbox_overview' + (qs.length ? '?' + qs.join('&') : '')).then(function (r) {
                var box = r.data.mailbox;
                if (!box) { return; }
                renderFolders(box.folders);
                renderMessages(box.messages, box.total);
            });
        }

        function renderFolders(folders) {
            P.renderList('folder-list', folders, function (f) {
                return '<li>' +
                    '<a href="#" data-folder="' + f.id + '" class="folder-link' + (f.id === currentFolder ? ' active' : '') + '">' +
                    P.esc(f.name) +
                    (f.unread > 0 ? ' <span class="badge unread">' + f.unread + '</span>' : '') +
                    '</a>' +
                    (f.is_system === 0 ? ' <button class="linklike rename" data-id="' + f.id + '">rename</button>' : '') +
                    '</li>';
            });
            document.querySelectorAll('.folder-link').forEach(function (a) {
                a.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    currentFolder = parseInt(a.getAttribute('data-folder'), 10);
                    load();
                });
            });
            document.querySelectorAll('.rename').forEach(function (b) {
                b.addEventListener('click', function () {
                    var name = prompt('New folder name:');
                    if (name) {
                        P.api('PATCH', '/api/mail/mailbox_overview/' + b.getAttribute('data-id'), { name: name }).then(function (res) {
                            P.flash((res.data && res.data.message) || 'Updated', res.ok ? 'ok' : 'error');
                            load();
                        });
                    }
                });
            });
        }

        function renderMessages(messages, total) {
            var host = P.el('message-list');
            if (!messages.length) {
                host.innerHTML = '<p class="muted empty">No messages.</p>';
                return;
            }
            host.innerHTML = messages.map(function (m) {
                return '<li class="msg-row">' +
                    '<a href="/message/' + m.id + '">' +
                    '<span class="' + (m.status === 'unread' ? 'badge unread' : 'badge') + '">' +
                    (m.status === 'unread' ? 'unread' : 'read') + '</span> ' +
                    '<strong>' + P.esc(m.from_name || m.from_address) + '</strong>' +
                    ' &mdash; ' + P.esc(m.subject) +
                    (m.attachments > 0 ? ' <span class="badge paperclip">&#128206;</span>' : '') +
                    ' <span class="muted">' + P.esc(m.created_at) + '</span>' +
                    '</a></li>';
            }).join('');
        }

        P.el('folder-form').addEventListener('submit', function (ev) {
            ev.preventDefault();
            P.api('POST', '/api/mail/mailbox_overview', { name: P.el('new-folder').value }).then(function (res) {
                P.flash((res.data && res.data.message) || 'Created', res.ok ? 'ok' : 'error');
                P.el('new-folder').value = '';
                load();
            });
        });

        P.el('search-form').addEventListener('submit', function (ev) {
            ev.preventDefault();
            currentQ = P.el('search-q').value.trim();
            load();
        });

        load();
    })();
</script>
