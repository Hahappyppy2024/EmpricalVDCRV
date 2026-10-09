(function() {
  var card = document.querySelector('[data-issue-id]');
  if (!card) return;
  var issueID = card.getAttribute('data-issue-id');
  var activity = document.getElementById('ws-activity');
  function append(entry) {
    if (!activity) return;
    var muted = activity.querySelector('.muted');
    if (muted) muted.remove();
    var li = document.createElement('li');
    li.textContent = '[' + entry.event + '] ' + entry.message + ' (' + entry.timestamp + ')';
    activity.prepend(li);
    while (activity.children.length > 30) {
      activity.removeChild(activity.lastChild);
    }
  }
  if (typeof WebSocket === 'undefined') {
    if (activity) activity.innerHTML = '<li class="muted">WebSocket not supported in this browser.</li>';
    return;
  }
  var proto = location.protocol === 'https:' ? 'wss' : 'ws';
  var ws;
  function connect() {
    ws = new WebSocket(proto + '://' + location.host + '/api/issue/issue_search/ws?issue=' + encodeURIComponent(issueID));
    ws.addEventListener('message', function(ev) {
      try {
        append(JSON.parse(ev.data));
      } catch (e) {
        console.warn('ws parse', e);
      }
    });
    ws.addEventListener('close', function() { setTimeout(connect, 2000); });
    ws.addEventListener('error', function() {
      try { ws.close(); } catch (e) {}
    });
  }
  connect();
})();