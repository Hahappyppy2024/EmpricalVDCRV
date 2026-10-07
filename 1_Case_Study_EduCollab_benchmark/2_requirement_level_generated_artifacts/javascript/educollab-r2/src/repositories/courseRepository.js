const { db } = require('../db');

function createCourse({ title, description, created_by }) {
  const stmt = db.prepare(`
    INSERT INTO courses (title, description, created_by)
    VALUES (?, ?, ?)
  `);
  const result = stmt.run(title, description || '', created_by);
  return findById(result.lastInsertRowid);
}

function listCourses() {
  return db.prepare(`
    SELECT
      c.course_id,
      c.title,
      c.description,
      c.created_by,
      c.created_at,
      u.username AS creator_username,
      u.display_name AS creator_display_name
    FROM courses c
    JOIN users u ON u.user_id = c.created_by
    ORDER BY c.course_id DESC
  `).all();
}

function findById(courseId) {
  return db.prepare(`
    SELECT
      c.course_id,
      c.title,
      c.description,
      c.created_by,
      c.created_at,
      u.username AS creator_username,
      u.display_name AS creator_display_name
    FROM courses c
    JOIN users u ON u.user_id = c.created_by
    WHERE c.course_id = ?
  `).get(courseId);
}

function updateCourse(courseId, { title, description }) {
  db.prepare(`
    UPDATE courses
    SET title = ?, description = ?
    WHERE course_id = ?
  `).run(title, description || '', courseId);
  return findById(courseId);
}

function deleteCourse(courseId) {
  const stmt = db.prepare(`DELETE FROM courses WHERE course_id = ?`);
  return stmt.run(courseId);
}

module.exports = {
  createCourse,
  listCourses,
  findById,
  updateCourse,
  deleteCourse
};
