// Stream runner events over WebSocket for live log output.
(function () {
  const el = document.getElementById('live-log');
  if (!el) return;
  const runID = el.dataset.runId;
  if (!runID) return;
  const proto = location.protocol === 'https:' ? 'wss' : 'ws';
  const ws = new WebSocket(proto + '://' + location.host + '/api/runs/' + runID + '/stream');
  ws.onmessage = function (ev) {
    try {
      const data = JSON.parse(ev.data);
      const node = document.createElement('div');
      node.className = 'event level-' + (data.level || 'info');
      node.textContent = '[' + (data.level || 'info').toUpperCase() + '] ' + data.message;
      el.appendChild(node);
      el.scrollTop = el.scrollHeight;
    } catch (e) {
      const node = document.createElement('div');
      node.className = 'event level-info';
      node.textContent = ev.data;
      el.appendChild(node);
    }
  };
  ws.onclose = function () {
    const node = document.createElement('div');
    node.className = 'event level-info';
    node.textContent = '[stream closed]';
    el.appendChild(node);
  };
})();