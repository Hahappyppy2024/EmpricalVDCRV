#!/usr/bin/env node
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { getDb, closeDb } from '../src/db/connection.js';
import { applySchema } from '../src/db/schema.js';
import { seedAll } from '../src/db/seed.js';
import { config } from '../src/config.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

function main() {
  const args = new Set(process.argv.slice(2));
  const dbFile = path.resolve(projectRoot, config.dbPath);
  const dbDir = path.dirname(dbFile);

  fs.mkdirSync(dbDir, { recursive: true });

  // Remove existing db + WAL + SHM files for a deterministic reset.
  for (const suffix of ['', '-wal', '-shm', '-journal']) {
    const target = dbFile + suffix;
    if (fs.existsSync(target)) {
      fs.unlinkSync(target);
      console.log(`[reset] removed ${target}`);
    }
  }

  getDb();
  applySchema();
  console.log('[reset] schema applied (version 1)');

  if (args.has('--seed') || args.has('--with-seed') || args.size === 0) {
    seedAll();
    console.log('[reset] deterministic seed fixtures inserted');
  }

  closeDb();
  console.log('[reset] database ready at ' + dbFile);
}

main();
