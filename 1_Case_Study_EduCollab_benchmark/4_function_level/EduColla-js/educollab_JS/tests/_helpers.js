// tests/_helpers.js
const fs = require('fs');
const path = require('path');
const request = require('supertest');

console.log('HELPERS VERSION = v3 (wipe src cache hard)');

function wipeCache(projectRoot) {
  const srcDir = path.join(projectRoot, 'src') + path.sep;
  const appJs = path.join(projectRoot, 'app.js');

  for (const k of Object.keys(require.cache)) {
    if (k === appJs || k.startsWith(srcDir)) {
      delete require.cache[k];
    }
  }
}

function makeAppWithFreshDb(tmpName = 'test.db') {
  const projectRoot = path.resolve(__dirname, '..');
  const tmpDir = path.join(__dirname, '.tmp');
  fs.mkdirSync(tmpDir, { recursive: true });

  const dbPath = path.join(tmpDir, tmpName);
  try { fs.unlinkSync(dbPath); } catch (e) {}

  process.env.DB_PATH = dbPath;

  // ✅ wipe everything under src/ + app.js
  wipeCache(projectRoot);

  // ✅ init schema on THIS db
  const { initDb, seedAdmin } = require(path.join(projectRoot, 'src', 'db'));
  initDb();
  seedAdmin();

  // ✅ now load app
  const app = require(path.join(projectRoot, 'app'));

  console.log('[HELPERS] DB_PATH =', process.env.DB_PATH);

  return { app, agent: request.agent(app), request, dbPath };
}

async function apiRegister(agent, { username, password = 'password', display_name = 'User' }) {
  return agent.post('/api/auth/register').set('Content-Type','application/json')
    .send({ username, password, display_name });
}
async function apiLogin(agent, { username, password }) {
  return agent.post('/api/auth/login').set('Content-Type','application/json')
    .send({ username, password });
}
async function apiCreateCourse(agent, { title = 'Course', description = 'Desc' }) {
  return agent.post('/api/courses').set('Content-Type','application/json')
    .send({ title, description });
}

async function apiCreateAssignment(agent, course_id, { title='A1', description='Asg', due_at } = {}) {
  const due = due_at || new Date(Date.now() + 3600 * 1000).toISOString();
  return agent
    .post(`/api/courses/${course_id}/assignments`)
    .set('Content-Type','application/json')
    .send({ title, description, due_at: due });
}
module.exports = { makeAppWithFreshDb, apiRegister, apiLogin,apiCreateAssignment, apiCreateCourse };