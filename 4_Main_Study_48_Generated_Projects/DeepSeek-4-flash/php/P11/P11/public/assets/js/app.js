(function () {
    'use strict';

    // Live audit feed over the optional Workerman WebSocket process.
    var auditTable = document.getElementById('audit-table');
    if (!auditTable) {
        return;
    }

    var scheme = location.protocol === 'https:' ? 'wss:' : 'ws:';
    var host = location.hostname || 'localhost';
    var port = 8081;

    function appendEvent(ev) {
        var tbody = auditTable.querySelector('tbody');
        if (!tbody) {
            return;
        }
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' + ev.id + '</td>' +
            '<td></td>' +
            '<td><span class="badge">' + ev.action + '</span></td>' +
            '<td>' + ev.module + '</td>' +
            '<td>' + (ev.entity_type || '') + ' #' + (ev.entity_id || '') + '</td>' +
            '<td>' + ev.details + '</td>' +
            '<td>' + ev.created_at + '</td>';
        tbody.insertBefore(tr, tbody.firstChild);
    }

    function connect() {
        var socket;
        try {
            socket = new WebSocket(scheme + '//' + host + ':' + port);
        } catch (e) {
            return;
        }
        socket.onmessage = function (msg) {
            try {
                var data = JSON.parse(msg.data);
                if (data.type === 'audit') {
                    appendEvent(data.event);
                }
            } catch (e) {
                // ignore malformed frames
            }
        };
        socket.onclose = function () {
            setTimeout(connect, 5000);
        };
    }

    setTimeout(connect, 500);
})();
