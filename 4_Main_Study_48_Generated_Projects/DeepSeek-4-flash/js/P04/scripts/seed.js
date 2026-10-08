import { initDatabase, closeDatabase } from '../src/db/database.js';
import { seedDatabase } from '../src/db/seed.js';
import { config } from '../src/config.js';

initDatabase();
const result = seedDatabase();
console.log(`Seeded ${Object.keys(result.roomIds).length} rooms and ${Object.keys(result.bookingIds).length} bookings at ${config.dbPath}`);
closeDatabase();
