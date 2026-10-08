import { WebSocketServer } from 'ws';

let wss = null;
const clients = new Set();

export function attachWs(server) {
  wss = new WebSocketServer({ server, path: '/ws' });
  wss.on('connection', (socket) => {
    clients.add(socket);
    socket.on('close', () => clients.delete(socket));
    socket.on('error', () => clients.delete(socket));
  });
  return wss;
}

export function broadcast(event, payload) {
  const message = JSON.stringify({ type: event, payload, timestamp: new Date().toISOString() });
  for (const socket of clients) {
    if (socket.readyState === socket.OPEN) {
      socket.send(message);
    }
  }
}

export function clientCount() {
  return clients.size;
}
