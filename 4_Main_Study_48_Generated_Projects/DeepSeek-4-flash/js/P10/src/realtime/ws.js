import { WebSocketServer } from 'ws';
import { parse } from 'node:url';
import { config } from '../config.js';

export function attachRealtime(server, db) {
  const wss = new WebSocketServer({ noServer: true });
  const connectionsByUser = new Map();

  server.on('upgrade', (req, socket, head) => {
    const url = parse(req.url ?? '', true);
    if (url.pathname !== '/ws') {
      socket.destroy();
      return;
    }
    const token = url.query.token ?? cookieValue(req.headers.cookie, config.sessionCookieName);
    if (!token) {
      socket.write('HTTP/1.1 401 Unauthorized\r\n\r\n');
      socket.destroy();
      return;
    }
    const row = db
      .prepare(
        `SELECT s.token, u.id, u.username, u.role, u.status FROM sessions s
         JOIN users u ON u.id = s.user_id WHERE s.token = ?`
      )
      .get(token);
    if (!row || row.status === 'suspended') {
      socket.write('HTTP/1.1 401 Unauthorized\r\n\r\n');
      socket.destroy();
      return;
    }
    req.realtimeUser = { id: row.id, username: row.username, role: row.role };
    wss.handleUpgrade(req, socket, head, (ws) => {
      wss.emit('connection', ws, req);
    });
  });

  wss.on('connection', (ws, req) => {
    const user = req.realtimeUser;
    if (!connectionsByUser.has(user.id)) {
      connectionsByUser.set(user.id, new Set());
    }
    connectionsByUser.get(user.id).add(ws);
    ws.send(JSON.stringify({ type: 'ws:connected', data: { user_id: user.id, at: new Date().toISOString() } }));
    ws.on('close', () => {
      const set = connectionsByUser.get(user.id);
      if (set) {
        set.delete(ws);
        if (set.size === 0) connectionsByUser.delete(user.id);
      }
    });
  });

  function broadcast(type, data, targetUserId = null) {
    const payload = JSON.stringify({ type, data });
    if (targetUserId) {
      const set = connectionsByUser.get(targetUserId);
      if (!set) return;
      for (const ws of set) {
        if (ws.readyState === ws.OPEN) ws.send(payload);
      }
      return;
    }
    for (const set of connectionsByUser.values()) {
      for (const ws of set) {
        if (ws.readyState === ws.OPEN) ws.send(payload);
      }
    }
  }

  return { broadcast, wss };
}

function cookieValue(cookieHeader, name) {
  if (!cookieHeader) return null;
  for (const part of cookieHeader.split(';')) {
    const [key, ...rest] = part.trim().split('=');
    if (key === name) return rest.join('=');
  }
  return null;
}
