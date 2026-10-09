/* P01 LMS frontend integration — exercises loading / validation / empty / error. */
(function () {
    'use strict';

    const bootstrap = window.lmsBootstrap || {};
    const endpoints = bootstrap.endpoints || {};

    function setState(el, state, message) {
        if (!el) return;
        el.dataset.state = state || 'idle';
        el.className = 'state-banner ' + (state || '');
        el.textContent = message || '';
    }

    function renderResults(target, results) {
        if (!target) return;
        target.innerHTML = '';
        if (!results || !results.length) {
            target.innerHTML = '<p class="empty">No results.</p>';
            return;
        }
        const ul = document.createElement('ul');
        ul.className = 'grid';
        results.forEach(function (row) {
            const li = document.createElement('li');
            li.className = 'card';
            li.innerHTML = '<h3>' + escapeHtml(row.code) + '</h3>'
                + '<p>' + escapeHtml(row.title) + '</p>'
                + '<p class="muted">' + escapeHtml(row.instructor_name || '') + '</p>';
            ul.appendChild(li);
        });
        target.appendChild(ul);
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'})[c];
        });
    }

    async function jsonFetch(url, opts) {
        const headers = Object.assign({
            'Accept': 'application/json',
            'X-CSRF-Token': bootstrap.csrf || ''
        }, (opts && opts.headers) || {});
        const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts || {}, { headers: headers }));
        const txt = await r.text();
        let body = null;
        try { body = JSON.parse(txt); } catch (e) { body = { raw: txt }; }
        return { ok: r.ok, status: r.status, body: body };
    }

    const discForm = document.getElementById('disc-form');
    if (discForm) {
        discForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const q = document.getElementById('disc-q').value.trim();
            const cat = document.getElementById('disc-cat').value;
            const sem = document.getElementById('disc-sem').value;
            const states = document.getElementById('disc-states');
            const out = document.getElementById('disc-results');
            setState(states, 'loading', 'Loading…');
            out.innerHTML = '';
            if (!q && !cat && !sem) {
                setState(states, 'error', 'Validation failed: provide at least one filter.');
                return;
            }
            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (cat) params.set('category', cat);
            if (sem) params.set('semester', sem);
            try {
                const r = await jsonFetch(endpoints.discovery + '?' + params.toString());
                if (!r.ok) {
                    setState(states, 'error', 'Server error: ' + (r.body && r.body.error ? r.body.error : r.status));
                    return;
                }
                const rows = (r.body && r.body.results) || [];
                if (!rows.length) {
                    setState(states, 'empty', 'No matching courses.');
                } else {
                    setState(states, 'ok', rows.length + ' courses found.');
                }
                renderResults(out, rows);
            } catch (err) {
                setState(states, 'error', 'Network error.');
            }
        });
    }

    const mineBtn = document.getElementById('mycourses-btn');
    if (mineBtn) {
        mineBtn.addEventListener('click', async function () {
            const state = document.getElementById('mycourses-state');
            const list = document.getElementById('mycourses-list');
            setState(state, 'loading', 'Loading my courses…');
            list.innerHTML = '';
            try {
                const r = await jsonFetch(endpoints.myCourses);
                if (!r.ok) {
                    setState(state, 'error', r.body && r.body.error ? r.body.error : 'Failed.');
                    return;
                }
                const rows = (r.body && r.body.enrollments) || [];
                if (!rows.length) {
                    setState(state, 'empty', 'You are not enrolled in any courses.');
                    return;
                }
                setState(state, 'ok', rows.length + ' enrollments.');
                list.className = 'grid';
                rows.forEach(function (c) {
                    const li = document.createElement('li');
                    li.className = 'card';
                    li.innerHTML = '<strong>' + escapeHtml(c.code) + '</strong><br>' + escapeHtml(c.title);
                    list.appendChild(li);
                });
            } catch (err) {
                setState(state, 'error', 'Network error.');
            }
        });
    }

    // Quiz form dynamic add-question / option helpers
    const addBtn = document.getElementById('add-question');
    const questionsHost = document.getElementById('questions');
    if (addBtn && questionsHost) {
        let idx = questionsHost.querySelectorAll('fieldset.question').length;
        addBtn.addEventListener('click', function () {
            const fs = document.createElement('fieldset');
            fs.className = 'question';
            fs.dataset.index = idx;
            fs.innerHTML = '<legend>Question ' + (idx + 1) + '</legend>'
                + '<label>Prompt <input name="questions[' + idx + '][prompt]" required></label>'
                + '<label>Kind <select name="questions[' + idx + '][kind]"><option value="single">single</option><option value="multi">multi</option><option value="short">short</option></select></label>'
                + '<label>Points <input type="number" step="0.1" name="questions[' + idx + '][points]" value="1"></label>'
                + '<div class="options"><div class="option-row"><input name="questions[' + idx + '][options][]"><button type="button" class="remove-option">×</button></div><div class="option-row"><input name="questions[' + idx + '][options][]"><button type="button" class="remove-option">×</button></div><button type="button" class="add-option btn btn-ghost">Add option</button></div>';
            questionsHost.appendChild(fs);
            idx++;
            wireQuestion(fs);
        });
        questionsHost.querySelectorAll('fieldset.question').forEach(wireQuestion);
    }

    function wireQuestion(fs) {
        fs.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-option')) {
                const row = e.target.closest('.option-row');
                row.parentNode.removeChild(row);
            }
            if (e.target.classList.contains('add-option')) {
                const idx = fs.dataset.index;
                const div = document.createElement('div');
                div.className = 'option-row';
                div.innerHTML = '<input name="questions[' + idx + '][options][]"><button type="button" class="remove-option">×</button>';
                e.target.parentNode.insertBefore(div, e.target);
            }
        });
    }
})();
