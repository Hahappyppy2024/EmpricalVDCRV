(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function toast(message, kind) {
        const el = document.getElementById('toast');
        if (!el) return;
        el.textContent = message;
        el.className = 'toast show ' + (kind || '');
        setTimeout(() => { el.className = 'toast'; }, 4000);
    }

    async function api(path, options) {
        const headers = options.headers || {};
        headers['X-CSRF-Token'] = csrf;
        if (options.body && !(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        const response = await fetch(path, Object.assign({ credentials: 'same-origin' }, options, { headers }));
        let data = null;
        try { data = await response.json(); } catch (_) { data = { status: response.status, text: await response.text() }; }
        if (!response.ok) {
            toast(data.error || ('request_failed_' + response.status), 'error');
            throw data;
        }
        return data;
    }

    document.querySelectorAll('form.api-form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const action = form.dataset.method || 'post';
            const data = new FormData(form);
            const target = form.action;
            const redirect = form.dataset.redirect;
            try {
                if (action === 'get') {
                    const params = new URLSearchParams(data).toString();
                    const result = await api(target + '?' + params, { method: 'GET' });
                    toast('search complete', 'success');
                    if (redirect) { window.location = redirect; }
                    return result;
                }
                if (action === 'patch' || action === 'put') {
                    const result = await api(target, { method: action.toUpperCase(), body: data });
                    toast('updated', 'success');
                    if (redirect) { window.location = redirect; }
                    return result;
                }
                const result = await api(target, { method: 'POST', body: data });
                toast('saved', 'success');
                if (redirect) { window.location = redirect; }
                return result;
            } catch (err) {
                if (err && err.error) { console.error(err); }
            }
        });
    });

    document.querySelectorAll('form.api-form[data-method="get"]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const data = new FormData(form);
            const params = new URLSearchParams(data);
            window.location = form.action + '?' + params.toString();
        });
    });
})();