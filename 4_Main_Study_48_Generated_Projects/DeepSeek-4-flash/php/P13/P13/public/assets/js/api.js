(function (window) {
    'use strict';

    function request(method, url, body) {
        var opts = {
            method: method,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': window.P13.csrf,
                'Accept': 'application/json'
            }
        };
        if (body !== undefined && body !== null) {
            if (body instanceof FormData) {
                opts.body = body;
            } else {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(body);
            }
        }
        return fetch(url, opts).then(function (res) {
            return res.json().then(function (data) {
                return { status: res.status, ok: res.ok, data: data };
            }).catch(function () {
                return { status: res.status, ok: res.ok, data: {} };
            });
        });
    }

    function el(id) {
        return document.getElementById(id);
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = (s === null || s === undefined) ? '' : String(s);
        return d.innerHTML;
    }

    function flash(msg, type) {
        var box = document.getElementById('flash');
        if (!box) { return; }
        box.textContent = msg || '';
        box.className = 'alert alert-' + (type || 'info');
        box.style.display = msg ? 'block' : 'none';
    }

    function renderList(id, items, fn) {
        var host = document.getElementById(id);
        if (!host) { return; }
        if (!items || !items.length) {
            host.innerHTML = '<p class="muted empty">No records.</p>';
            return;
        }
        host.innerHTML = items.map(fn).join('');
    }

    window.P13 = window.P13 || {};
    window.P13.api = request;
    window.P13.el = el;
    window.P13.esc = esc;
    window.P13.flash = flash;
    window.P13.renderList = renderList;
})(window);
