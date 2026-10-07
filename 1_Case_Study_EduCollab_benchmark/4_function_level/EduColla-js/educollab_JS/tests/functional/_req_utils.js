const stream = require('stream');
const archiver = require('archiver');

function zipBuffer(entries) {
  return new Promise((resolve, reject) => {
    const archive = archiver('zip', { zlib: { level: 9 } });
    const bufs = [];
    const pass = new stream.PassThrough();
    pass.on('data', (d) => bufs.push(d));
    pass.on('end', () => resolve(Buffer.concat(bufs)));
    archive.on('error', reject);
    archive.pipe(pass);
    for (const e of entries) archive.append(e.content, { name: e.name });
    archive.finalize();
  });
}

async function getUserIdByUsername(adminAgent, username) {
  const usersRes = await adminAgent.get('/api/users');
  if (usersRes.status !== 200) throw new Error(`GET /api/users failed: ${usersRes.status}`);
  const users = usersRes.body.users || usersRes.body;
  const u = users.find(x => x.username === username);
  if (!u) throw new Error(`user not found: ${username}`);
  return u.user_id;
}

module.exports = { zipBuffer, getUserIdByUsername };
