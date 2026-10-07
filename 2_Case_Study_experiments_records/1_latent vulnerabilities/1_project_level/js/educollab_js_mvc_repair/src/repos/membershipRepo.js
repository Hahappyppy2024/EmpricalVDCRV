const { db, nowIso } = require('../db');

function addMember({ course_id, user_id, role_in_course }) {
  const info = db
    .prepare('INSERT INTO memberships (course_id, user_id, role_in_course, created_at) VALUES (?,?,?,?)')
    .run(course_id, user_id, role_in_course, nowIso());
  return getById(info.lastInsertRowid);
}

function getById(membership_id) {
  return db.prepare('SELECT * FROM memberships WHERE membership_id=?').get(membership_id);
}

function getByCourseAndUser(course_id, user_id) {
  return db.prepare('SELECT * FROM memberships WHERE course_id=? AND user_id=?').get(course_id, user_id);
}

function listMembers(course_id) {
  return db
    .prepare(
      `
    SELECT m.*, u.username, u.display_name
    FROM memberships m
    JOIN users u ON u.user_id = m.user_id
    WHERE m.course_id=?
    ORDER BY m.membership_id ASC
  `
    )
    .all(course_id);
}

function updateRole(membership_id, role_in_course) {
  db.prepare('UPDATE memberships SET role_in_course=? WHERE membership_id=?').run(role_in_course, membership_id);
  return getById(membership_id);
}

function updateRoleInCourse(course_id, membership_id, role_in_course) {
  const info = db
    .prepare('UPDATE memberships SET role_in_course=? WHERE course_id=? AND membership_id=?')
    .run(role_in_course, course_id, membership_id);
  if (!info || info.changes === 0) return null;
  return getById(membership_id);
}

function removeMember(membership_id) {
  db.prepare('DELETE FROM memberships WHERE membership_id=?').run(membership_id);
}

function removeMemberInCourse(course_id, membership_id) {
  const info = db
    .prepare('DELETE FROM memberships WHERE course_id=? AND membership_id=?')
    .run(course_id, membership_id);
  return !!(info && info.changes > 0);
}

function myMemberships(user_id) {
  return db
    .prepare(
      `
    SELECT m.*, c.title as course_title
    FROM memberships m
    JOIN courses c ON c.course_id = m.course_id
    WHERE m.user_id=?
    ORDER BY m.membership_id DESC
  `
    )
    .all(user_id);
}

module.exports = {
  addMember,
  getById,
  getByCourseAndUser,
  listMembers,
  updateRole,
  updateRoleInCourse,
  removeMember,
  removeMemberInCourse,
  myMemberships
};