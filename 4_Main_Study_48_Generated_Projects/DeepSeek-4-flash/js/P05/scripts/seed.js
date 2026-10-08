import { openDb, closeDb } from '../src/db/index.js';
import { seed } from '../src/db/seed.js';
import env from '../src/config/env.js';

const db = openDb(env.dbPath);
const result = seed(db);
closeDb();

if (result.skipped) {
  console.log('[seed] Nothing to do - run `npm run reset-db` first for a clean reseed.');
}
