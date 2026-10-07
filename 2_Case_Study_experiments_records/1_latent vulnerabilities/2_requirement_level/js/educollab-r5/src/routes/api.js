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
const questionController = require('../controllers/questionController');
const quizController = require('../controllers/quizController');

const {
  requireAuthApi,
  requireAdminApi,
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

router.post('/courses/:course_id/members', requireTeacherOrAdmin, membershipController.apiAddMember);
router.get('/courses/:course_id/members', requireCourseMember, membershipController.apiListMembers);
router.put('/courses/:course_id/members/:membership_id', requireTeacherOrAdmin, membershipController.apiUpdateMemberRole);
router.delete('/courses/:course_id/members/:membership_id', requireTeacherOrAdmin, membershipController.apiRemoveMember);
router.get('/memberships', requireAuthApi, membershipController.apiMyMemberships);

router.post('/courses/:course_id/posts', requireCourseMember, postController.apiCreatePost);
router.get('/courses/:course_id/posts', requireCourseMember, postController.apiListPosts);
router.get('/courses/:course_id/posts/:post_id', requireCourseMember, postController.apiGetPost);
router.put('/courses/:course_id/posts/:post_id', requireCourseMember, postController.apiUpdatePost);
router.delete('/courses/:course_id/posts/:post_id', requireCourseMember, postController.apiDeletePost);

router.post('/courses/:course_id/posts/:post_id/comments', requireCourseMember, commentController.apiCreateComment);
router.get('/courses/:course_id/posts/:post_id/comments', requireCourseMember, commentController.apiListComments);
router.put('/courses/:course_id/posts/:post_id/comments/:comment_id', requireCourseMember, commentController.apiUpdateComment);
router.delete('/courses/:course_id/posts/:post_id/comments/:comment_id', requireCourseMember, commentController.apiDeleteComment);

router.get('/courses/:course_id/search/posts', requireCourseMember, postController.apiSearchPosts);
router.get('/courses/:course_id/search/comments', requireCourseMember, commentController.apiSearchComments);

router.post('/courses/:course_id/assignments', requireCourseStaff, assignmentController.apiCreateAssignment);
router.get('/courses/:course_id/assignments', requireCourseMember, assignmentController.apiListAssignments);
router.get('/courses/:course_id/assignments/:assignment_id', requireCourseMember, assignmentController.apiGetAssignment);
router.put('/courses/:course_id/assignments/:assignment_id', requireCourseStaff, assignmentController.apiUpdateAssignment);
router.delete('/courses/:course_id/assignments/:assignment_id', requireCourseStaff, assignmentController.apiDeleteAssignment);

router.post('/courses/:course_id/assignments/:assignment_id/submissions', requireCourseMember, submissionController.apiCreateSubmission);
router.put('/courses/:course_id/assignments/:assignment_id/submissions/:submission_id', requireCourseMember, submissionController.apiUpdateMySubmission);
router.get('/my/submissions', requireAuthApi, submissionController.apiListMySubmissions);
router.get('/courses/:course_id/assignments/:assignment_id/submissions', requireCourseMember, submissionController.apiListSubmissionsForAssignment);

router.post('/courses/:course_id/uploads', requireCourseStaff, upload.single('file'), uploadController.apiCreateUpload);
router.get('/courses/:course_id/uploads', requireCourseMember, uploadController.apiListUploads);
router.get('/courses/:course_id/uploads/:upload_id/download', requireCourseMember, uploadController.apiDownloadUpload);
router.delete('/courses/:course_id/uploads/:upload_id', requireCourseStaff, uploadController.apiDeleteUpload);

router.post('/courses/:course_id/questions', requireCourseStaff, questionController.apiCreateQuestion);
router.get('/courses/:course_id/questions', requireCourseMember, questionController.apiListQuestions);
router.get('/courses/:course_id/questions/:question_id', requireCourseMember, questionController.apiGetQuestion);
router.put('/courses/:course_id/questions/:question_id', requireCourseStaff, questionController.apiUpdateQuestion);
router.delete('/courses/:course_id/questions/:question_id', requireCourseStaff, questionController.apiDeleteQuestion);

router.post('/courses/:course_id/quizzes', requireCourseStaff, quizController.apiCreateQuiz);
router.get('/courses/:course_id/quizzes', requireCourseMember, quizController.apiListQuizzes);
router.get('/courses/:course_id/quizzes/:quiz_id', requireCourseMember, quizController.apiGetQuiz);
router.put('/courses/:course_id/quizzes/:quiz_id', requireCourseStaff, quizController.apiUpdateQuiz);
router.delete('/courses/:course_id/quizzes/:quiz_id', requireCourseStaff, quizController.apiDeleteQuiz);
router.post('/courses/:course_id/quizzes/:quiz_id/questions', requireCourseStaff, quizController.apiConfigureQuizQuestions);
router.delete('/courses/:course_id/quizzes/:quiz_id/questions/:question_id', requireCourseStaff, quizController.apiRemoveQuizQuestion);

router.post('/courses/:course_id/quizzes/:quiz_id/attempts/start', requireCourseMember, quizController.apiStartAttempt);
router.post('/courses/:course_id/quizzes/:quiz_id/attempts/:attempt_id/answers', requireCourseMember, quizController.apiAnswerQuestion);
router.post('/courses/:course_id/quizzes/:quiz_id/attempts/:attempt_id/submit', requireCourseMember, quizController.apiSubmitAttempt);
router.get('/my/quizzes/attempts', requireAuthApi, quizController.apiViewMyAttempts);

module.exports = router;
