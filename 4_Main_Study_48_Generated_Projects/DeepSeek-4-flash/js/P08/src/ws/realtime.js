import http from 'node:http';
import { WebSocketServer } from 'ws';
import { config } from '../config.js';

export function createRealtime(httpServer) {
  const wss = new WebSocketServer({ server: httpServer, path: config.wsPath });
  const clients = new Set();

  wss.on('connection', (socket) => {
    clients.add(socket);
    socket.on('close', () => clients.delete(socket));
    socket.on('error', () => clients.delete(socket));
    socket.send(JSON.stringify({ type: 'connected', data: { message: 'Real-time channel ready' } }));
  });

  function broadcast(message) {
    const payload = JSON.stringify(message);
    for (const client of clients) {
      if (client.readyState === client.OPEN) {
        client.send(payload);
      }
    }
  }

  return { wss, broadcast };
}

export { http };
