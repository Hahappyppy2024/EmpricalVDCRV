(function () {
    'use strict';

    function toast(message, isError) {
        var el = document.getElementById('realtime-toast');
        if (!el) { return; }
        el.textContent = message;
        el.style.background = isError ? '#b91c1c' : '#101828';
        el.hidden = false;
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.hidden = true; }, 4000);
    }

    function api(path, options) {
        options = options || {};
        options.headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, options.headers || {});
        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        return fetch(path, options).then(function (res) {
            return res.json().catch(function () { return { ok: false, error: 'Invalid response' }; }).then(function (data) {
                data._status = res.status;
                return data;
            });
        });
    }

    window.CFS = {
        toast: toast,
        api: api,
        esc: function (s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        },
        formatBytes: function (n) {
            n = Number(n) || 0;
            if (n < 1024) { return n + ' B'; }
            if (n < 1048576) { return (n / 1024).toFixed(1) + ' KiB'; }
            if (n < 1073741824) { return (n / 1048576).toFixed(1) + ' MiB'; }
            return (n / 1073741824).toFixed(2) + ' GiB';
        }
    };

    document.addEventListener('click', function (e) {
        var t = e.target;
        var el = t.closest ? t.closest('[data-confirm]') : null;
        if (el && !window.confirm(el.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    });

    function connectRealtime() {
        var port = window.CFS_WS_PORT || 8282;
        var proto = location.protocol === 'https:' ? 'wss' : 'ws';
        var ws;
        function open() {
            try {
                ws = new WebSocket(proto + '://' + location.hostname + ':' + port);
            } catch (err) { return; }
            ws.onmessage = function (ev) {
                try {
                    var msg = JSON.parse(ev.data);
                    if (msg.event) {
                        toast('Live: ' + msg.event + (msg.file ? ' — ' + msg.file : ''));
                    }
                } catch (err) { /* ignore */ }
            };
            ws.onclose = function () { setTimeout(open, 8000); };
        }
        open();
    }

    var pages = document.body.getAttribute('data-page');
    if (pages) { connectRealtime(); }

    function runModule() {
        var page = document.body.getAttribute('data-page');
        if (!page) { return; }
        var ns = window.CFS[page];
        if (ns && typeof ns.init === 'function') { ns.init(); }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runModule);
    } else {
        runModule();
    }
})();
