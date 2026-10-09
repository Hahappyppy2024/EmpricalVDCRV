import { WebSocketServer } from 'ws';

const subscribers = new Map();
let wssInstance = null;

export function setupWebSocket(server) {
  wssInstance = new WebSocketServer({ server, path: '/ws/exp' });
  wssInstance.on('connection', (ws, req) => {
    ws.subscriptions = new Set();
    ws.send(JSON.stringify({ type: 'hello', message: 'Connected to expense approval websocket' }));
    ws.on('message', (raw) => {
      try {
        const msg = JSON.parse(raw.toString());
        if (msg.action === 'subscribe' && msg.reportId) {
          ws.subscriptions.add(Number(msg.reportId));
          ws.send(JSON.stringify({ type: 'subscribed', reportId: msg.reportId }));
        }
        if (msg.action === 'unsubscribe' && msg.reportId) {
          ws.subscriptions.delete(Number(msg.reportId));
          ws.send(JSON.stringify({ type: 'unsubscribed', reportId: msg.reportId }));
        }
      } catch (_) { /* ignore */ }
    });
    ws.on('close', () => {
      for (const id of ws.subscriptions) {
        const set = subscribers.get(id);
        if (set) {
          set.delete(ws);
          if (set.size === 0) subscribers.delete(id);
        }
      }
    });
  });
  console.log('WebSocket server attached at /ws/exp');
}

export function notifyReportSubscribers(reportId, payload) {
  const set = subscribers.get(Number(reportId));
  if (!set || !wssInstance) return;
  const data = JSON.stringify({ type: 'report_event', reportId: Number(reportId), ...payload });
  for (const ws of set) {
    if (ws.readyState === 1) {
      try { ws.send(data); } catch (_) { /* ignore */ }
    }
  }
}

export function registerSubscriber(ws, reportId) {
  const id = Number(reportId);
  if (!subscribers.has(id)) subscribers.set(id, new Set());
  subscribers.get(id).add(ws);
}
