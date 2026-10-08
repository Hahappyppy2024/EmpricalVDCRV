import { openDatabase, closeDatabase } from '../src/db/database.js';
import { seedDatabase } from '../src/db/seed.js';

const db = openDatabase();
const result = seedDatabase(db, { force: false });
closeDatabase(db);
console.log(
  result.seeded
    ? 'Database seeded with fixtures.'
    : 'Database already contains seed data; skipped (use `npm run db:reset` to reseed).'
);
