<h1>Compose message</h1>
<div id="flash" class="alert" style="display:none"></div>

<form id="compose-form" class="card stack">
    <input type="hidden" id="compose-id" value="<?= e($draft_id ?? '') ?>">
    <label>To (comma-separated)
        <input type="text" id="recipient" placeholder="bob@example.com" required>
    </label>
    <datalist id="contact-list"></datalist>
    <label>Subject
        <input type="text" id="subject" required>
    </label>
    <label>Body
        <textarea id="body" rows="10"></textarea>
    </label>
    <div class="row">
        <button type="submit" class="btn" id="btn-send">Send</button>
        <button type="button" class="btn btn-secondary" id="btn-draft">Save draft</button>
        <span class="muted" id="attachments-hint"></span>
    </div>
</form>

<script>
    (function () {
        var P = window.P13;

        P.api('GET', '/api/mail/message_compose').then(function (r) {
            var data = r.data;
            if (data.contacts) {
                var dl = P.el('contact-list');
                dl.innerHTML = data.contacts.map(function (c) {
                    return '<option value="' + P.esc(c.email) + '">' + P.esc((c.first_name || '') + ' ' + (c.last_name || '')) + '</option>';
                }).join('');
            }
            if (data.compose && data.compose.items) {
                var draft = data.compose.items.find(function (d) {
                    return String(d.id) === String(P.el('compose-id').value);
                });
                if (draft) {
                    P.el('recipient').value = draft.recipient;
                    P.el('subject').value = draft.subject;
                    P.el('body').value = draft.body;
                }
            }
        });

        function submit(action) {
            var payload = {
                action: action,
                recipient: P.el('recipient').value,
                subject: P.el('subject').value,
                body: P.el('body').value
            };
            var id = P.el('compose-id').value;
            if (id) { payload.draft_id = parseInt(id, 10); }
            P.api('POST', '/api/mail/message_compose', payload).then(function (res) {
                P.flash((res.data && res.data.message) || (res.ok ? 'Done' : 'Failed'), res.ok ? 'ok' : 'error');
                if (res.ok && action === 'send') {
                    P.el('recipient').value = '';
                    P.el('subject').value = '';
                    P.el('body').value = '';
                    P.el('compose-id').value = '';
                }
            });
        }

        P.el('btn-send').addEventListener('click', function (ev) {
            ev.preventDefault();
            submit('send');
        });
        P.el('btn-draft').addEventListener('click', function () {
            submit('draft');
        });
    })();
</script>
