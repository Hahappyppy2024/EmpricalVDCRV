import { getDb, runSchema } from './connection.js';

const db = getDb();
runSchema(db);
console.log('[init] schema applied at', process.env.DATABASE_PATH || './data/hotel.db');
db.close();