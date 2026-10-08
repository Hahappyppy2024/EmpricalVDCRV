import { WebSocketServer } from 'ws';

let wss = null;

export function initRealTime(server) {
  wss = new WebSocketServer({ server, path: '/ws' });
  wss.on('connection', (ws) => {
    ws.isAlive = true;
    ws.on('pong', () => { ws.isAlive = true; });
    ws.on('message', (raw) => {
      try {
        const data = JSON.parse(String(raw));
        if (data && data.type === 'ping') {
          ws.send(JSON.stringify({ type: 'pong', data: { ts: Date.now() } }));
        }
      } catch {
        // ignore malformed frames
      }
    });
  });

  const interval = setInterval(() => {
    if (!wss) return;
    for (const ws of wss.clients) {
      if (ws.isAlive === false) {
        ws.terminate();
        continue;
      }
      ws.isAlive = false;
      try {
        ws.ping();
      } catch {
        // client gone
      }
    }
  }, 30000);
  wss.on('close', () => clearInterval(interval));

  // eslint-disable-next-line no-console
  console.log('[ws] WebSocket server mounted at /ws');
  return wss;
}

export function broadcast(event, payload) {
  if (!wss) return 0;
  const message = JSON.stringify({ type: event, data: payload, ts: Date.now() });
  let sent = 0;
  for (const client of wss.clients) {
    if (client.readyState === 1) {
      try {
        client.send(message);
        sent += 1;
      } catch {
        // skip
      }
    }
  }
  return sent;
}

export function isRealtimeEnabled(db) {
  const row = db.prepare("SELECT value FROM plugin_settings_panel WHERE key = 'realtime_enabled'").get();
  return !row || row.value === 'true';
}
