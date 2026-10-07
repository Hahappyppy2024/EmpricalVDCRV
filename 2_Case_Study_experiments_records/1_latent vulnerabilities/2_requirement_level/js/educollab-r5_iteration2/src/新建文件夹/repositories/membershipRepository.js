const { db } = require('../db');

function addMember({ course_id, user_id, role_in_course }) {
  const result = db.prepare(`
    INSERT INTO memberships (course_id, user_id, role_in_course)
    VALUES (?, ?, ?)
  `).run(course_id, user_id, role_in_course);
  return findById(result.lastInsertRowid);
}

function findById(membershipId) {
  return db.prepare(`
    SELECT m.*, u.username, u.display_name, c.title AS course_title
    FROM memberships m
    JOIN users u ON u.user_id = m.user_id
    JOIN courses c ON c.course_id = m.course_id
    WHERE membership_id = ?
  `).get(membershipId);
}

function findByCourseAndUser(courseId, userId) {
  return db.prepare(`
    SELECT m.*, u.username, u.display_name
    FROM memberships m
    JOIN users u ON u.user_id = m.user_id
    WHERE m.course_id = ? AND m.user_id = ?
  `).get(courseId, userId);
}

function listMembersByCourse(courseId) {
  return db.prepare(`
    SELECT m.*, u.username, u.display_name
    FROM memberships m
    JOIN users u ON u.user_id = m.user_id
    WHERE m.course_id = ?
    ORDER BY m.membership_id ASC
  `).all(courseId);
}

function updateMemberRole(membershipId, role_in_course) {
  db.prepare(`
    UPDATE memberships SET role_in_course = ?
    WHERE membership_id = ?
  `).run(role_in_course, membershipId);
  return findById(membershipId);
}

function removeMember(membershipId) {
  return db.prepare('DELETE FROM memberships WHERE membership_id = ?').run(membershipId);
}

function listMembershipsByUser(userId) {
  return db.prepare(`
    SELECT m.*, c.title AS course_title
    FROM memberships m
    JOIN courses c ON c.course_id = m.course_id
    WHERE m.user_id = ?
    ORDER BY m.membership_id DESC
  `).all(userId);
}

module.exports = {
  addMember,
  findById,
  findByCourseAndUser,
  listMembersByCourse,
  updateMemberRole,
  removeMember,
  listMembershipsByUser
};
