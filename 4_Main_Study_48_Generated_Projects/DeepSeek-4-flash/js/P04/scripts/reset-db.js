import { resetDatabase, closeDatabase } from '../src/db/database.js';
import { seedDatabase } from '../src/db/seed.js';
import { config } from '../src/config.js';

resetDatabase();
seedDatabase();
console.log(`Database reset and seeded at: ${config.dbPath}`);
closeDatabase();
