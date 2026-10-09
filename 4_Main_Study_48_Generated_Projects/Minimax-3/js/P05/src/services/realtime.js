const clients = new Set();

export function registerClient(ws) {
  clients.add(ws);
}

export function unregisterClient(ws) {
  clients.delete(ws);
}

export function broadcast(event, payload) {
  const msg = JSON.stringify({ type: event, payload });
  for (const c of clients) {
    if (c.readyState === 1) c.send(msg);
  }
}

export function clientCount() {
  return clients.size;
}
