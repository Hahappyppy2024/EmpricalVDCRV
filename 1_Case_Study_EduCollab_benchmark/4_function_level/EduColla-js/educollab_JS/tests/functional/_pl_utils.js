async function getUserIdByUsername(adminAgent, username) {
  const usersRes = await adminAgent.get('/api/users');
  if (usersRes.status !== 200) {
    throw new Error(`GET /api/users failed: status=${usersRes.status} body=${JSON.stringify(usersRes.body)} text=${usersRes.text}`);
  }
  const users = usersRes.body.users || usersRes.body;
  const u = users.find(x => x.username === username);
  if (!u) throw new Error(`user not found: ${username}`);
  return u.user_id;
}

async function addMemberByUsername(adminAgent, course_id, username, role_in_course='student') {
  const user_id = await getUserIdByUsername(adminAgent, username);
  const add = await adminAgent.post(`/api/courses/${course_id}/members`).send({ user_id, role_in_course });
  if (![200, 201].includes(add.status)) {
    throw new Error(`add member failed: status=${add.status} body=${JSON.stringify(add.body)} text=${add.text}`);
  }
  return add;
}

module.exports = { getUserIdByUsername, addMemberByUsername };
