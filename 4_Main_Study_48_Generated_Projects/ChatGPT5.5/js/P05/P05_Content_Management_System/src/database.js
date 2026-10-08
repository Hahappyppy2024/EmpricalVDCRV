import Database from 'better-sqlite3';import fs from 'node:fs';import path from 'node:path';
export function openDatabase(file){if(file!==':memory:')fs.mkdirSync(path.dirname(file),{recursive:true});const db=new Database(file);db.pragma('foreign_keys = ON');return db;}
export function resetDatabase(file,root){const db=openDatabase(file);db.exec(fs.readFileSync(path.join(root,'database/schema.sql'),'utf8'));return db;}
