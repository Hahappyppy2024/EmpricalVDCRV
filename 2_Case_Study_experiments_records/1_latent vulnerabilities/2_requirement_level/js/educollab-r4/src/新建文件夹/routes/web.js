const express = require('express');
const path = require('path');
const fs = require('fs');
const multer = require('multer');

const authController = require('../controllers/authController');
const userController = require('../controllers/userController');
const courseController = require('../controllers/courseController');
const membershipController = require('../controllers/membershipController');
const postController = require('../controllers/postController');
const commentController = require('../controllers/commentController');
const assignmentController = require('../controllers/assignmentController');
const submissionController = require('../controllers/submissionController');
const uploadController = require('../controllers/uploadController');

const {
  requireAuthPage,
  requireAdminPage,
  requireCourseMember,
  requireTeacherOrAdmin,
  requireCourseStaff
} = require('../middleware/auth');

const router = express.Router();

const uploadDir = path.join(__dirname, '..', '..', 'storage', 'uploads');
fs.mkdirSync(uploadDir, { recursive: true });

const upload = multer({
  storage: multer.diskStorage({
    destination: function (req, file, cb) {
      cb(null, uploadDir);
    },
    filename: function (req, file, cb) {
      const unique = `${Date.now()}-${Math.random().toString(36).slice(2)}-${file.originalname}`;
      cb(null, unique);
    }
  })
});

router.get('/', (req, res) => res.render('index'));

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

router.get('/courses/:course_id/members/new', requireTeacherOrAdmin, membershipController.showAddMember);
router.post('/courses/:course_id/members', requireTeacherOrAdmin, membershipController.addMember);
router.get('/courses/:course_id/members', requireCourseMember, membershipController.listMembers);
router.post('/courses/:course_id/members/:membership_id', requireTeacherOrAdmin, membershipController.updateMemberRole);
router.post('/courses/:course_id/members/:membership_id/delete', requireTeacherOrAdmin, membershipController.removeMember);
router.get('/memberships', requireAuthPage, membershipController.myMemberships);

router.get('/courses/:course_id/posts/new', requireCourseMember, postController.showNewPost);
router.post('/courses/:course_id/posts', requireCourseMember, postController.createPost);
router.get('/courses/:course_id/posts', requireCourseMember, postController.listPosts);
router.get('/courses/:course_id/posts/:post_id', requireCourseMember, postController.getPost);
router.get('/courses/:course_id/posts/:post_id/edit', requireCourseMember, postController.showEditPost);
router.post('/courses/:course_id/posts/:post_id', requireCourseMember, postController.updatePost);
router.post('/courses/:course_id/posts/:post_id/delete', requireCourseMember, postController.deletePost);

router.post('/courses/:course_id/posts/:post_id/comments', requireCourseMember, commentController.createComment);
router.get('/courses/:course_id/posts/:post_id/comments', requireCourseMember, commentController.listComments);
router.post('/courses/:course_id/posts/:post_id/comments/:comment_id', requireCourseMember, commentController.updateComment);
router.post('/courses/:course_id/posts/:post_id/comments/:comment_id/delete', requireCourseMember, commentController.deleteComment);

router.get('/courses/:course_id/search', requireCourseMember, postController.searchPosts);
router.get('/courses/:course_id/search/comments', requireCourseMember, commentController.searchComments);

router.get('/courses/:course_id/assignments/new', requireCourseStaff, assignmentController.showNewAssignment);
router.post('/courses/:course_id/assignments', requireCourseStaff, assignmentController.createAssignment);
router.get('/courses/:course_id/assignments', requireCourseMember, assignmentController.listAssignments);
router.get('/courses/:course_id/assignments/:assignment_id', requireCourseMember, assignmentController.getAssignment);
router.get('/courses/:course_id/assignments/:assignment_id/edit', requireCourseStaff, assignmentController.showEditAssignment);
router.post('/courses/:course_id/assignments/:assignment_id', requireCourseStaff, assignmentController.updateAssignment);
router.post('/courses/:course_id/assignments/:assignment_id/delete', requireCourseStaff, assignmentController.deleteAssignment);

router.get('/courses/:course_id/assignments/:assignment_id/submit', requireCourseMember, submissionController.showSubmit);
router.post('/courses/:course_id/assignments/:assignment_id/submissions', requireCourseMember, submissionController.createSubmission);
router.post('/courses/:course_id/assignments/:assignment_id/submissions/:submission_id', requireCourseMember, submissionController.updateMySubmission);
router.get('/my/submissions', requireAuthPage, submissionController.listMySubmissions);
router.get('/courses/:course_id/assignments/:assignment_id/submissions', requireCourseMember, submissionController.listSubmissionsForAssignment);

router.get('/courses/:course_id/uploads/new', requireCourseStaff, uploadController.showUploadForm);
router.post('/courses/:course_id/uploads', requireCourseStaff, upload.single('file'), uploadController.createUpload);
router.get('/courses/:course_id/uploads', requireCourseMember, uploadController.listUploads);
router.get('/courses/:course_id/uploads/:upload_id/download', requireCourseMember, uploadController.downloadUpload);
router.post('/courses/:course_id/uploads/:upload_id/delete', requireCourseStaff, uploadController.deleteUpload);

module.exports = router;
