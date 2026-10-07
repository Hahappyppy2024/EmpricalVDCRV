const { db, nowIso } = require('../db');

function createCourse({ title, description, created_by }) {
  const info = db.prepare(
    'INSERT INTO courses (title, description, created_by, created_at) VALUES (?,?,?,?)'
  ).run(title, description, created_by, nowIso());
  return getById(info.lastInsertRowid);
}

function listCourses() {
  return db.prepare(`
    SELECT c.*, u.username as created_by_username
    FROM courses c
    JOIN users u ON u.user_id = c.created_by
    ORDER BY c.course_id DESC
  `).all();
}

function getById(course_id) {
  return db.prepare('SELECT * FROM courses WHERE course_id=?').get(course_id);
}

function updateCourse(course_id, { title, description }) {
  db.prepare('UPDATE courses SET title=?, description=? WHERE course_id=?').run(title, description, course_id);
  return getById(course_id);
}

function deleteCourse(course_id) {
  db.prepare('DELETE FROM courses WHERE course_id=?').run(course_id);
}

module.exports = { createCourse, listCourses, getById, updateCourse, deleteCourse };
