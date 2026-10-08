<h1>Filters and rules</h1>
<div id="flash" class="alert" style="display:none"></div>

<div class="grid">
    <div class="card">
        <h2>New rule</h2>
        <form id="rule-form" class="stack">
            <input type="text" id="r-name" placeholder="Rule name" required>
            <select id="r-field">
                <option value="from">From</option>
                <option value="to">To</option>
                <option value="subject">Subject</option>
                <option value="body">Body</option>
            </select>
            <select id="r-op">
                <option value="contains">contains</option>
                <option value="equals">equals</option>
            </select>
            <input type="text" id="r-value" placeholder="Match value" required>
            <select id="r-action">
                <option value="folder">Move to folder</option>
                <option value="label">Apply label</option>
                <option value="forward">Forward</option>
                <option value="delete">Delete</option>
            </select>
            <input type="text" id="r-action-value" placeholder="Target folder / label / address">
            <label class="row"><input type="checkbox" id="r-enabled" checked> Enabled</label>
            <button type="submit" class="btn">Create rule</button>
        </form>
    </div>
    <div class="card">
        <h2>My rules</h2>
        <ul id="rule-list" class="plain"></ul>
    </div>
</div>

<script>
    (function () {
        var P = window.P13;

        function load() {
            P.api('GET', '/api/mail/filters_and_rules').then(function (r) {
                P.renderList('rule-list', r.data.rules.items || [], function (rule) {
                    return '<li>' +
                        '<label class="row"><input type="checkbox" data-rule="' + rule.id + '"' + (rule.enabled === 1 ? ' checked' : '') + '> ' +
                        '<strong>' + P.esc(rule.name) + '</strong></label> ' +
                        '<span class="muted">' + P.esc(rule.match_field + ' ' + rule.match_operator + ' "' + rule.match_value + '"') +
                        ' &rarr; ' + P.esc(rule.action_type) + ' ' + P.esc(rule.action_value) + '</span>' +
                        '</li>';
                });
                document.querySelectorAll('[data-rule]').forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        P.api('PATCH', '/api/mail/filters_and_rules/' + cb.getAttribute('data-rule'), { enabled: cb.checked ? 1 : 0 }).then(function () {
                            load();
                        });
                    });
                });
            });
        }

        P.el('rule-form').addEventListener('submit', function (ev) {
            ev.preventDefault();
            P.api('POST', '/api/mail/filters_and_rules', {
                name: P.el('r-name').value,
                match_field: P.el('r-field').value,
                match_operator: P.el('r-op').value,
                match_value: P.el('r-value').value,
                action_type: P.el('r-action').value,
                action_value: P.el('r-action-value').value,
                enabled: P.el('r-enabled').checked ? 1 : 0
            }).then(function (res) {
                P.flash((res.data && res.data.message) || 'Created', res.ok ? 'ok' : 'error');
                P.el('r-name').value = P.el('r-value').value = P.el('r-action-value').value = '';
                load();
            });
        });

        load();
    })();
</script>
