<h1>Contact management</h1>
<div id="flash" class="alert" style="display:none"></div>

<div class="grid">
    <div class="card">
        <h2>Add contact</h2>
        <form id="contact-form" class="stack">
            <input type="text" id="c-first" placeholder="First name">
            <input type="text" id="c-last" placeholder="Last name">
            <input type="email" id="c-email" placeholder="email@example.com" required>
            <input type="text" id="c-phone" placeholder="Phone">
            <input type="text" id="c-org" placeholder="Organization">
            <button type="submit" class="btn">Add contact</button>
        </form>
    </div>
    <div class="card">
        <h2>Contacts</h2>
        <form id="search-form" class="stack row">
            <input type="text" id="search-q" placeholder="Search contacts">
            <button type="submit" class="btn btn-small">Search</button>
        </form>
        <ul id="contact-list" class="plain"></ul>
    </div>
</div>

<script>
    (function () {
        var P = window.P13;

        function load(q) {
            var url = '/api/mail/contact_management';
            if (q) { url += '?q=' + encodeURIComponent(q); }
            P.api('GET', url).then(function (r) {
                P.renderList('contact-list', r.data.contacts.items || [], function (c) {
                    return '<li>' +
                        '<strong>' + P.esc(c.first_name + ' ' + c.last_name).trim() + '</strong> ' +
                        '&lt;' + P.esc(c.email) + '&gt;' +
                        (c.organization ? ' <span class="muted">(' + P.esc(c.organization) + ')</span>' : '') +
                        '</li>';
                });
            });
        }

        P.el('contact-form').addEventListener('submit', function (ev) {
            ev.preventDefault();
            P.api('POST', '/api/mail/contact_management', {
                first_name: P.el('c-first').value,
                last_name: P.el('c-last').value,
                email: P.el('c-email').value,
                phone: P.el('c-phone').value,
                organization: P.el('c-org').value
            }).then(function (res) {
                P.flash((res.data && res.data.message) || 'Added', res.ok ? 'ok' : 'error');
                P.el('c-first').value = P.el('c-last').value = P.el('c-email').value = P.el('c-phone').value = P.el('c-org').value = '';
                load(P.el('search-q').value.trim());
            });
        });

        P.el('search-form').addEventListener('submit', function (ev) {
            ev.preventDefault();
            load(P.el('search-q').value.trim());
        });

        load('');
    })();
</script>
