import { config, ensureDirs } from './config.js';
import { createApp } from './app.js';

ensureDirs();
const { httpServer } = createApp();

httpServer.listen(config.port, config.host, () => {
  console.log(`[P08] Enterprise Expense Approval System running at http://${config.host}:${config.port}`);
  console.log(`[P08] Real-time channel on ws://${config.host}:${config.port}${config.wsPath}`);
});
