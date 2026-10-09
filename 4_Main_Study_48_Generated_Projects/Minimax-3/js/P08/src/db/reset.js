import fs from 'node:fs';
import { createDatabase, DB_FILE, initSchema } from './index.js';
import { seed } from './seed.js';

console.log('Resetting database...');
try { fs.unlinkSync(DB_FILE); } catch (_) { /* ignore */ }
try { fs.unlinkSync(DB_FILE + '-wal'); } catch (_) { /* ignore */ }
try { fs.unlinkSync(DB_FILE + '-shm'); } catch (_) { /* ignore */ }

const fresh = createDatabase(DB_FILE);
try {
  initSchema(fresh);
  seed(fresh);
} finally {
  fresh.close();
}

console.log('Database reset and seed complete at', DB_FILE);
