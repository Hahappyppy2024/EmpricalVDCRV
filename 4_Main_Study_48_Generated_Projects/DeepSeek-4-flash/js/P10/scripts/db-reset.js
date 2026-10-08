import fs from 'node:fs';
import path from 'node:path';
import { config, ROOT_DIR } from '../src/config.js';
import { openDatabase, closeDatabase } from '../src/db/database.js';
import { seedDatabase } from '../src/db/seed.js';

console.log('Resetting database and uploads...');
for (const target of [config.databasePath, `${config.databasePath}-wal`, `${config.databasePath}-shm`]) {
  if (fs.existsSync(target)) {
    fs.rmSync(target, { force: true });
  }
}
if (fs.existsSync(config.uploadDir)) {
  fs.rmSync(config.uploadDir, { recursive: true, force: true });
}
fs.mkdirSync(config.uploadDir, { recursive: true });

const db = openDatabase();
const result = seedDatabase(db, { force: true });
closeDatabase(db);
console.log(`Done. ${result.seeded ? 'Database seeded with fixtures.' : 'Nothing to seed.'}`);
console.log('Seed accounts: admin/admin123 (admin), alice/alice123 (user), bob/bob123 (user)');
