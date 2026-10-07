const express = require('express');
const authController = require('../controllers/authController');
const userController = require('../controllers/userController');
const courseController = require('../controllers/courseController');
const membershipController = require('../controllers/membershipController');
const postController = require('../controllers/postController');
const commentController = require('../controllers/commentController');
const {
  requireAuthPage,
  requireAdminPage,
  loadCourseMembership,
  requireTeacherOrAdmin,
  requireCourseMember
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

router.get('/courses/:course_id/members', requireAuthPage, membershipController.listMembers);
router.get('/courses/:course_id/members/new', requireAuthPage, loadCourseMembership, requireTeacherOrAdmin, membershipController.showAddMember);
router.post('/courses/:course_id/members', requireAuthPage, loadCourseMembership, requireTeacherOrAdmin, membershipController.addMember);
router.post('/courses/:course_id/members/:membership_id', requireAuthPage, loadCourseMembership, requireTeacherOrAdmin, membershipController.updateMemberRole);
router.post('/courses/:course_id/members/:membership_id/delete', requireAuthPage, loadCourseMembership, requireTeacherOrAdmin, membershipController.removeMember);
router.get('/memberships', requireAuthPage, membershipController.myMemberships);

router.get('/courses/:course_id/posts/new', requireAuthPage, loadCourseMembership, requireCourseMember, postController.showNewPost);
router.post('/courses/:course_id/posts', requireAuthPage, loadCourseMembership, requireCourseMember, postController.createPost);
router.get('/courses/:course_id/posts', requireAuthPage, loadCourseMembership, requireCourseMember, postController.listPosts);
router.get('/courses/:course_id/posts/:post_id', requireAuthPage, loadCourseMembership, requireCourseMember, postController.getPost);
router.get('/courses/:course_id/posts/:post_id/edit', requireAuthPage, loadCourseMembership, requireCourseMember, postController.showEditPost);
router.post('/courses/:course_id/posts/:post_id', requireAuthPage, loadCourseMembership, requireCourseMember, postController.updatePost);
router.post('/courses/:course_id/posts/:post_id/delete', requireAuthPage, loadCourseMembership, requireCourseMember, postController.deletePost);

router.post('/courses/:course_id/posts/:post_id/comments', requireAuthPage, loadCourseMembership, requireCourseMember, commentController.createComment);
router.get('/courses/:course_id/posts/:post_id/comments', requireAuthPage, loadCourseMembership, requireCourseMember, postController.listComments);
router.post('/courses/:course_id/posts/:post_id/comments/:comment_id', requireAuthPage, loadCourseMembership, requireCourseMember, commentController.updateComment);
router.post('/courses/:course_id/posts/:post_id/comments/:comment_id/delete', requireAuthPage, loadCourseMembership, requireCourseMember, commentController.deleteComment);

router.get('/courses/:course_id/search', requireAuthPage, loadCourseMembership, requireCourseMember, postController.searchPosts);
router.get('/courses/:course_id/search/comments', requireAuthPage, loadCourseMembership, requireCourseMember, commentController.searchComments);

module.exports = router;
