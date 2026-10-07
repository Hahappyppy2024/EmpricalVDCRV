const express = require('express');
const authController = require('../controllers/authController');
const userController = require('../controllers/userController');
const courseController = require('../controllers/courseController');
const {
  requireAuthApi,
  requireAdminApi
} = require('../middleware/auth');

const router = express.Router();

router.post('/auth/register', authController.apiRegister);
router.post('/auth/login', authController.apiLogin);
router.post('/auth/logout', authController.apiLogout);
router.get('/auth/me', authController.apiMe);

router.get('/users', requireAuthApi, requireAdminApi, userController.listUsersApi);

router.post('/courses', requireAuthApi, courseController.apiCreateCourse);
router.get('/courses', requireAuthApi, courseController.apiListCourses);
router.get('/courses/:course_id', requireAuthApi, courseController.apiGetCourse);
router.put('/courses/:course_id', requireAuthApi, courseController.apiUpdateCourse);
router.delete('/courses/:course_id', requireAuthApi, courseController.apiDeleteCourse);

module.exports = router;
