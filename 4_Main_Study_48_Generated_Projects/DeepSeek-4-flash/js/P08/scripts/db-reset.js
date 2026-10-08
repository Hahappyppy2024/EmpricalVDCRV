import fs from 'node:fs';
import path from 'node:path';
import { resetDatabase, closeDb } from '../src/db/database.js';
import { seedDatabase } from '../src/db/seed.js';
import { config, ensureDirs } from '../src/config.js';

ensureDirs();
for (const f of fs.readdirSync(config.uploadDir)) {
  fs.rmSync(path.join(config.uploadDir, f), { force: true });
}

const db = resetDatabase();
seedDatabase(db);
const counts = {
  departments: db.prepare('SELECT COUNT(*) AS n FROM departments').get().n,
  cost_centers: db.prepare('SELECT COUNT(*) AS n FROM cost_centers').get().n,
  categories: db.prepare('SELECT COUNT(*) AS n FROM categories').get().n,
  users: db.prepare('SELECT COUNT(*) AS n FROM users').get().n,
  reports: db.prepare('SELECT COUNT(*) AS n FROM expense_reports').get().n,
  policy_rules: db.prepare('SELECT COUNT(*) AS n FROM policy_rules').get().n,
};
closeDb();
console.log('Database reset and seeded successfully.');
console.log(JSON.stringify(counts, null, 2));
