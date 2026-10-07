const express = require('express');
const authController = require('../controllers/authController');
const userController = require('../controllers/userController');
const courseController = require('../controllers/courseController');
const {
  requireAuthPage,
  requireAdminPage
} = require('../middleware/auth');

const router = express.Router();

router.get('/', (req, res) => {
  res.render('index');
});

router.get('/register', authController.showRegister);
router.post('/register', authController.register);

router.get('/login', authController.showLogin);
router.post('/login', authController.login);

router.post('/logout', authController.logout);
router.get('/me', authController.me);

router.get('/admin/users', requireAuthPage, requireAdminPage, userController.listUsersPage);

router.get('/courses/new', requireAuthPage, courseController.showNewCourse);
router.post('/courses', requireAuthPage, courseController.createCourse);
router.get('/courses', requireAuthPage, courseController.listCourses);
router.get('/courses/:course_id', requireAuthPage, courseController.getCourse);
router.get('/courses/:course_id/edit', requireAuthPage, courseController.showEditCourse);
router.post('/courses/:course_id', requireAuthPage, courseController.updateCourse);
router.post('/courses/:course_id/delete', requireAuthPage, courseController.deleteCourse);

module.exports = router;
