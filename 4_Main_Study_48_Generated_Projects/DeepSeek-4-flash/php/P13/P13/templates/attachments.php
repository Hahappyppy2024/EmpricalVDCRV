<h1>Attachment handling</h1>
<div id="flash" class="alert" style="display:none"></div>

<div class="card">
    <h2>Upload attachment</h2>
    <form id="upload-form" class="stack row">
        <input type="file" name="file" id="upload-file" required>
        <button type="submit" class="btn">Upload</button>
    </form>
</div>

<div class="card">
    <h2>My attachments</h2>
    <ul id="attachment-list" class="plain"></ul>
</div>

<script>
    (function () {
        var P = window.P13;

        function load() {
            P.api('GET', '/api/mail/attachment_handling').then(function (r) {
                P.renderList('attachment-list', r.data.attachments.items || [], function (a) {
                    return '<li>' +
                        '<span class="badge ' + (a.attachment_status === 'deleted' ? 'err' : 'ok') + '">' + P.esc(a.attachment_status) + '</span> ' +
                        '<strong>' + P.esc(a.original_name) + '</strong> <span class="muted">(' + a.size + ' bytes, ' +
                        P.esc(a.mime_type) + ')</span>' +
                        (a.message_subject ? ' &mdash; on "' + P.esc(a.message_subject) + '"' : '') +
                        ' <a href="/api/mail/attachment_handling/' + a.file_id + '/download">download</a>' +
                        '</li>';
                });
            });
        }

        P.el('upload-form').addEventListener('submit', function (ev) {
            ev.preventDefault();
            var fd = new FormData();
            fd.append('file', P.el('upload-file').files[0]);
            P.api('POST', '/api/mail/attachment_handling', fd).then(function (res) {
                P.flash((res.data && res.data.message) || 'Uploaded', res.ok ? 'ok' : 'error');
                P.el('upload-file').value = '';
                load();
            });
        });

        load();
    })();
</script>
