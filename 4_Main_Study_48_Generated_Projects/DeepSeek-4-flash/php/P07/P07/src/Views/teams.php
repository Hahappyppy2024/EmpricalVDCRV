<?php $page_title = 'Team spaces'; $page_slug = 'teams'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>
<div class="grid">
    <div class="card">
        <h2>Create team space</h2>
        <form id="team-create">
            <label>Team name</label>
            <input type="text" name="name" required>
            <label>Description</label>
            <textarea name="description"></textarea>
            <div style="margin-top:1rem"><button class="btn" type="submit">Create team</button></div>
        </form>
        <p id="team-result"></p>
    </div>
    <div class="card">
        <h2>Your team spaces</h2>
        <div id="team-list"><p class="meta">Loading…</p></div>
    </div>
</div>
<script>
window.CFS.teams = {
    init: function () {
        var self = this;
        var form = document.getElementById('team-create');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            CFS.api('/api/file/team_spaces', { method: 'POST', body: { name: form.name.value, description: form.description.value } }).then(function (data) {
                var out = document.getElementById('team-result');
                out.className = data.ok ? 'alert alert-ok' : 'alert alert-error';
                out.textContent = data.ok ? data.message : ((data.errors && data.errors[0]) || 'Failed');
                form.reset();
                self.refresh();
            });
        });
        self.refresh();
    },
    refresh: function () {
        CFS.api('/api/file/team_spaces').then(function (data) {
            var el = document.getElementById('team-list');
            if (!data.ok || !data.teams.length) {
                el.innerHTML = '<p class="meta">You are not a member of any team yet.</p>';
                return;
            }
            el.innerHTML = data.teams.map(function (t) {
                var members = t.members.map(function (m) { return '<span class="badge ' + (m.role === 'owner' ? 'badge-blue' : 'badge-green') + '">' + CFS.esc(m.username) + '</span>'; }).join(' ');
                var files = (t.files || []).map(function (f) { return CFS.esc(f.name); }).join(', ') || 'no files shared yet';
                return '<div class="card"><h3>' + CFS.esc(t.name) + '</h3>' +
                    '<p class="meta">' + CFS.esc(t.description || '') + '</p>' +
                    '<p><strong>Members:</strong> ' + members + '</p>' +
                    '<p><strong>Files:</strong> <span class="meta">' + files + '</span></p></div>';
            }).join('');
        });
    }
};
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
