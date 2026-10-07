const stream = require('stream');
const archiver = require('archiver');

function zipBuffer(entries) {
  return new Promise((resolve, reject) => {
    const archive = archiver('zip', { zlib: { level: 9 } });
    const chunks = [];
    const pass = new stream.PassThrough();
    pass.on('data', (d) => chunks.push(d));
    pass.on('end', () => resolve(Buffer.concat(chunks)));
    archive.on('error', reject);
    archive.pipe(pass);
    for (const e of entries) archive.append(e.content, { name: e.name });
    archive.finalize();
  });
}

async function importRosterCsvAsAdmin(agent, course_id, csvText) {
  const roster = Buffer.from(csvText);
  return agent
    .post(`/api/courses/${course_id}/members/import`)
    .attach('csv', roster, { filename: 'roster.csv', contentType: 'text/csv' });
}

module.exports = { zipBuffer, importRosterCsvAsAdmin };
