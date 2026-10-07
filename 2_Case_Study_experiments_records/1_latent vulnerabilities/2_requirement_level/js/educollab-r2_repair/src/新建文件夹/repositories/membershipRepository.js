const { db } = require('../db');

const ALLOWED_ROLES = ['admin', 'teacher', 'student', 'assistant', 'senior-assistant'];

function isValidRole(role) {
  return ALLOWED_ROLES.includes(role);
}

function addMembership({ course_id, user_id, role_in_course }) {
  const stmt = db.prepare(`
    INSERT INTO memberships (course_id, user_id, role_in_course)
    VALUES (?, ?, ?)
  `);
  const result = stmt.run(course_id, user_id, role_in_course);
  return findById(result.lastInsertRowid);
}

function findById(membershipId) {
  return db.prepare(`
    SELECT
      m.membership_id,
      m.course_id,
      m.user_id,
      m.role_in_course,
      m.created_at,
      u.username,
      u.display_name,
      c.title AS course_title
    FROM memberships m
    JOIN users u ON u.user_id = m.user_id
    JOIN courses c ON c.course_id = m.course_id
    WHERE m.membership_id = ?
  `).get(membershipId);
}

function findByCourseAndUser(courseId, userId) {
  return db.prepare(`
    SELECT
      m.membership_id,
      m.course_id,
      m.user_id,
      m.role_in_course,
      m.created_at,
      u.username,
      u.display_name,
      c.title AS course_title
    FROM memberships m
    JOIN users u ON u.user_id = m.user_id
    JOIN courses c ON c.course_id = m.course_id
    WHERE m.course_id = ? AND m.user_id = ?
  `).get(courseId, userId);
}

function listByCourse(courseId) {
  return db.prepare(`
    SELECT
      m.membership_id,
      m.course_id,
      m.user_id,
      m.role_in_course,
      m.created_at,
      u.username,
      u.display_name
    FROM memberships m
    JOIN users u ON u.user_id = m.user_id
    WHERE m.course_id = ?
    ORDER BY m.membership_id ASC
  `).all(courseId);
}

function listByUser(userId) {
  return db.prepare(`
    SELECT
      m.membership_id,
      m.course_id,
      m.user_id,
      m.role_in_course,
      m.created_at,
      c.title AS course_title,
      c.description AS course_description
    FROM memberships m
    JOIN courses c ON c.course_id = m.course_id
    WHERE m.user_id = ?
    ORDER BY m.membership_id ASC
  `).all(userId);
}

function updateMembershipRole(membershipId, role_in_course) {
  db.prepare(`
    UPDATE memberships
    SET role_in_course = ?
    WHERE membership_id = ?
  `).run(role_in_course, membershipId);
  return findById(membershipId);
}

function removeMembership(membershipId) {
  return db.prepare(`DELETE FROM memberships WHERE membership_id = ?`).run(membershipId);
}

module.exports = {
  ALLOWED_ROLES,
  isValidRole,
  addMembership,
  findById,
  findByCourseAndUser,
  listByCourse,
  listByUser,
  updateMembershipRole,
  removeMembership
};
