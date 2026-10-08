<h1>Frontend API integration &amp; errors</h1>
<div id="flash" class="alert" style="display:none"></div>

<p class="muted">This page exercises the frontend/API error handling contract (MAIL-12): delivery
    failures, invalid recipients and permission errors are triggered against the real API and
    recorded as response states below.</p>

<div class="grid">
    <div class="card">
        <h2>Trigger scenarios</h2>
        <button class="btn btn-block" id="btn-invalid-recipient">Send to invalid recipient (422)</button>
        <button class="btn btn-block" id="btn-permission">Access admin endpoint as mail user (403)</button>
        <button class="btn btn-block" id="btn-delivery">Simulate delivery failure (502)</button>
    </div>
    <div class="card">
        <h2>Recorded response states</h2>
        <ul id="error-list" class="plain"></ul>
    </div>
</div>

<script>
    (function () {
        var P = window.P13;

        function record(scenario, status, message, payload) {
            return P.api('POST', '/api/mail/frontend_api_integration_and_errors', {
                scenario: scenario,
                response_status: status,
                response_message: message,
                request_payload: payload || {}
            }).then(function (res) {
                P.flash(message + ' (HTTP ' + status + ')', status >= 400 ? 'error' : 'ok');
                load();
                return res;
            });
        }

        function load() {
            P.api('GET', '/api/mail/frontend_api_integration_and_errors').then(function (r) {
                P.renderList('error-list', r.data.errors.items || [], function (e) {
                    return '<li>' +
                        '<span class="badge ' + (e.resolved === 1 ? 'ok' : e.response_status >= 500 ? 'err' : 'warn') + '">' +
                        'HTTP ' + e.response_status + '</span> ' +
                        '<strong>' + P.esc(e.scenario) + '</strong>' +
                        ' <span class="muted">' + P.esc(e.response_message) + '</span>' +
                        (e.resolved === 1 ? ' <span class="badge ok">resolved</span>' : ' <button class="linklike resolve" data-id="' + e.id + '">mark resolved</button>') +
                        '</li>';
                });
                document.querySelectorAll('.resolve').forEach(function (b) {
                    b.addEventListener('click', function () {
                        P.api('PATCH', '/api/mail/frontend_api_integration_and_errors/' + b.getAttribute('data-id'), { resolved: 1 }).then(function () {
                            load();
                        });
                    });
                });
            });
        }

        P.el('btn-invalid-recipient').addEventListener('click', function () {
            var payload = { to: 'nobody@missing.tld', action: 'send' };
            P.api('POST', '/api/mail/message_compose', {
                action: 'send',
                recipient: payload.to,
                subject: 'Test delivery',
                body: 'Testing invalid recipient handling.'
            }).then(function (res) {
                var msg = (res.data && res.data.error) || (res.data && res.data.errors && Object.values(res.data.errors).join(' ')) || 'Invalid recipient';
                record('invalid_recipient', res.status, msg, payload);
            });
        });

        P.el('btn-permission').addEventListener('click', function () {
            P.api('GET', '/api/mail/admin_audit_logs').then(function (res) {
                var msg = (res.data && res.data.error) || 'Permission denied';
                record('permission_denied', res.status, msg, { action: 'admin_audit_logs' });
            });
        });

        P.el('btn-delivery').addEventListener('click', function () {
            record('delivery_failure', 502, 'Delivery temporarily failed', { to: 'bob@example.com', retry: true });
        });

        load();
    })();
</script>
