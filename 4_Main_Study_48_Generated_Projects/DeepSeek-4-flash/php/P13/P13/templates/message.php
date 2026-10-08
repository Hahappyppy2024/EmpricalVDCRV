<h1>Message</h1>
<div id="flash" class="alert" style="display:none"></div>

<div class="card" id="message-view">
    <p class="muted">Loading message&hellip;</p>
</div>

<script>
    (function () {
        var P = window.P13;
        var messageId = <?= e($message_id) ?>;

        P.api('POST', '/api/mail/message_reading', { message_id: messageId });

        P.api('GET', '/api/mail/message_reading/' + messageId).then(function (r) {
            var m = r.data.message;
            if (!m) {
                P.el('message-view').innerHTML = '<p class="alert alert-error">Message unavailable.</p>';
                return;
            }
            var html = '<h2>' + P.esc(m.subject) + '</h2>' +
                '<p class="meta">From <strong>' + P.esc(m.from_name || m.from_address) + '</strong> ' +
                '&lt;' + P.esc(m.from_address) + '&gt; &middot; To ' + P.esc(m.to_address) +
                ' &middot; ' + P.esc(m.created_at) + '</p>' +
                '<pre class="message-body">' + P.esc(m.body) + '</pre>';

            if (m.attachments_list && m.attachments_list.length) {
                html += '<h3>Attachments</h3><ul class="plain">' + m.attachments_list.map(function (f) {
                    return '<li><a href="/api/mail/attachment_handling/' + f.id + '/download">' +
                        P.esc(f.original_name) + '</a> <span class="muted">(' + f.size + ' bytes)</span></li>';
                }).join('') + '</ul>';
            }

            if (m.thread && m.thread.length) {
                html += '<h3>Thread</h3><ul class="plain">' + m.thread.map(function (t) {
                    return '<li><a href="/message/' + t.id + '">' + P.esc(t.subject) + '</a> ' +
                        '<span class="muted">' + P.esc(t.from_address) + ' &middot; ' + P.esc(t.created_at) + '</span></li>';
                }).join('') + '</ul>';
            }

            html += '<p><a class="btn btn-small btn-secondary" href="/mailbox">&larr; Back to mailbox</a></p>';
            P.el('message-view').innerHTML = html;
        });
    })();
</script>
