const express = require('express');
const authController = require('../controllers/authController');
const userController = require('../controllers/userController');
const courseController = require('../controllers/courseController');
const membershipController = require('../controllers/membershipController');
const {
  requireAuthApi,
  requireAdminApi,
  loadCourseMembership,
  requireTeacherOrAdmin
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

router.post('/courses/:course_id/members', requireAuthApi, loadCourseMembership, requireTeacherOrAdmin, membershipController.apiAddMember);
router.get('/courses/:course_id/members', requireAuthApi, membershipController.apiListMembers);
router.put('/courses/:course_id/members/:membership_id', requireAuthApi, loadCourseMembership, requireTeacherOrAdmin, membershipController.apiUpdateMemberRole);
router.delete('/courses/:course_id/members/:membership_id', requireAuthApi, loadCourseMembership, requireTeacherOrAdmin, membershipController.apiRemoveMember);
router.get('/memberships', requireAuthApi, membershipController.apiMyMemberships);

module.exports = router;
