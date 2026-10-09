import { initSchema } from './database.js';
import { seed } from './seed.js';

initSchema();
seed();
console.log('Database reset and seeded.');
