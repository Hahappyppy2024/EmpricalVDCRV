import { openDatabase, closeDb } from '../src/db/database.js';
import { seedDatabase } from '../src/db/seed.js';

const db = openDatabase();
seedDatabase(db);
const count = db.prepare('SELECT COUNT(*) AS n FROM users').get().n;
closeDb();
console.log(`Database seeded successfully. ${count} users available.`);
