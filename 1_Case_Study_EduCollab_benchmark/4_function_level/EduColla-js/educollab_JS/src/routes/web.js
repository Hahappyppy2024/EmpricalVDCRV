const express = require('express');


const router = express.Router();

const auth = require('../middleware/auth');

const authCtrl = require('../controllers/authController');
const userCtrl = require('../controllers/userController');
const courseCtrl = require('../controllers/courseController');
const membershipCtrl = require('../controllers/membershipController');
const postCtrl = require('../controllers/postController');
const commentCtrl = require('../controllers/commentController');
const searchCtrl = require('../controllers/searchController');
const assignmentCtrl = require('../controllers/assignmentController');
const submissionCtrl = require('../controllers/submissionController');
const submissionZipCtrl = require('../controllers/submissionZipController');
const assignmentExportCtrl = require('../controllers/assignmentExportController');
const uploadCtrl = require('../controllers/uploadController');
const materialsCtrl = require('../controllers/materialsController');
const csvCtrl = require('../controllers/csvController');
const questionCtrl = require('../controllers/questionController');
const quizCtrl = require('../controllers/quizController');
const takeQuizCtrl = require('../controllers/takeQuizController');
const auditController = require('../controllers/auditController');
const inviteCtrl = require('../controllers/inviteController');
const joinCtrl = require('../controllers/joinController');

// console.log('DEBUG inviteCtrl keys =', Object.keys(inviteCtrl));
console.log('submissionCtrl keys =', Object.keys(submissionCtrl));
console.log('typeof submitForm =', typeof submissionCtrl.submitForm);


// B) Auth / User
router.get('/register', authCtrl.registerForm);
router.post('/register', authCtrl.register);
router.get('/login', authCtrl.loginForm);
router.post('/login', authCtrl.login);
router.post('/logout', authCtrl.logout);
router.get('/me', auth.requireLogin, authCtrl.me);


// Optional admin users page
router.get('/admin/users', auth.requireLogin, auth.requireAdmin, userCtrl.listUsers);

// C) Courses
// R01_test1
router.get('/courses', auth.requireLogin, courseCtrl.listCourses);

router.get('/courses/new', auth.requireLogin, courseCtrl.newCourseForm);
router.post('/courses', auth.requireLogin, courseCtrl.createCourse);
router.get('/courses/:course_id', auth.requireLogin, auth.requireCourseMember, courseCtrl.getCourse);
router.get('/courses/:course_id/edit', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, courseCtrl.editCourseForm);
router.post('/courses/:course_id', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, courseCtrl.updateCourse);
router.post('/courses/:course_id/delete', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, courseCtrl.deleteCourse);

// D) Membership
router.get('/courses/:course_id/members', auth.requireLogin, auth.requireCourseMember, membershipCtrl.listMembers);
router.get('/courses/:course_id/members/new', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, membershipCtrl.newMemberForm);
router.post('/courses/:course_id/members', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, membershipCtrl.addMember);
router.post('/courses/:course_id/members/:membership_id', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, membershipCtrl.updateMemberRole);
router.post('/courses/:course_id/members/:membership_id/delete', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, membershipCtrl.removeMember);
router.get('/memberships', auth.requireLogin, membershipCtrl.myMemberships);

// E) Posts
router.get('/courses/:course_id/posts', auth.requireLogin, auth.requireCourseMember, postCtrl.listPosts);
router.get('/courses/:course_id/posts/new', auth.requireLogin, auth.requireCourseMember, postCtrl.newPostForm);
router.post('/courses/:course_id/posts', auth.requireLogin, auth.requireCourseMember, postCtrl.createPost);
router.get('/courses/:course_id/posts/:post_id', auth.requireLogin, auth.requireCourseMember, postCtrl.getPost);
router.get('/courses/:course_id/posts/:post_id/edit', auth.requireLogin, auth.requireCourseMember, postCtrl.editPostForm);
router.post('/courses/:course_id/posts/:post_id', auth.requireLogin, auth.requireCourseMember, postCtrl.updatePost);
router.post('/courses/:course_id/posts/:post_id/delete', auth.requireLogin, auth.requireCourseMember, postCtrl.deletePost);

// F) Comments
router.post('/courses/:course_id/posts/:post_id/comments', auth.requireLogin, auth.requireCourseMember, commentCtrl.createComment);
router.post('/courses/:course_id/posts/:post_id/comments/:comment_id', auth.requireLogin, auth.requireCourseMember, commentCtrl.updateComment);
router.post('/courses/:course_id/posts/:post_id/comments/:comment_id/delete', auth.requireLogin, auth.requireCourseMember, commentCtrl.deleteComment);

// G) Search
router.get('/courses/:course_id/search', auth.requireLogin, auth.requireCourseMember, searchCtrl.searchPosts);
router.get('/courses/:course_id/search/comments', auth.requireLogin, auth.requireCourseMember, searchCtrl.searchComments);

// H) Assignments
router.get('/courses/:course_id/assignments', auth.requireLogin, auth.requireCourseMember, assignmentCtrl.listAssignments);
// R01
router.get('/courses/:course_id/assignments/new', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, assignmentCtrl.newAssignmentForm);
router.post('/courses/:course_id/assignments', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, assignmentCtrl.createAssignment);
router.get('/courses/:course_id/assignments/:assignment_id', auth.requireLogin, auth.requireCourseMember, assignmentCtrl.getAssignment);
router.get('/courses/:course_id/assignments/:assignment_id/edit', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, assignmentCtrl.editAssignmentForm);
router.post('/courses/:course_id/assignments/:assignment_id', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, assignmentCtrl.updateAssignment);
router.post('/courses/:course_id/assignments/:assignment_id/delete', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, assignmentCtrl.deleteAssignment);

// I) Submissions
router.get('/courses/:course_id/assignments/:assignment_id/submit', auth.requireLogin, auth.requireCourseMember, auth.requireStudent, submissionCtrl.submitForm);
router.post('/courses/:course_id/assignments/:assignment_id/submissions', auth.requireLogin, auth.requireCourseMember, auth.requireStudent, submissionCtrl.createSubmission);
router.post('/courses/:course_id/assignments/:assignment_id/submissions/:submission_id', auth.requireLogin, auth.requireCourseMember, auth.requireStudent, submissionCtrl.updateMySubmission);
router.get('/my/submissions', auth.requireLogin, submissionCtrl.listMySubmissions);
router.get('/courses/:course_id/assignments/:assignment_id/submissions', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, submissionCtrl.listForAssignment);


// Function-level: Submission ZIP extract/list + export submissions zip + grades import
router.post('/courses/:course_id/assignments/:assignment_id/submissions/:submission_id/unzip', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, submissionZipCtrl.unzipSubmissionAttachment);
router.get('/courses/:course_id/assignments/:assignment_id/submissions/:submission_id/files', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, submissionZipCtrl.listSubmissionExtractedFiles);

router.post('/courses/:course_id/assignments/:assignment_id/submissions/export', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, assignmentExportCtrl.exportAssignmentSubmissionsZip);
router.get('/courses/:course_id/assignments/:assignment_id/submissions/export/:export_id/download', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, assignmentExportCtrl.downloadExportedSubmissionsZip);

router.get('/courses/:course_id/assignments/:assignment_id/grades/import', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, csvCtrl.importAssignmentGradesForm);
router.post('/courses/:course_id/assignments/:assignment_id/grades/import', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, csvCtrl.uploader.single('csv'), csvCtrl.importAssignmentGradesCsv);

// J) Uploads
router.get('/courses/:course_id/uploads', auth.requireLogin, auth.requireCourseMember, uploadCtrl.listUploads);
router.get('/courses/:course_id/uploads/new', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, uploadCtrl.newUploadForm);
router.post('/courses/:course_id/uploads', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, uploadCtrl.uploader.single('file'), uploadCtrl.uploadFile);
router.get('/courses/:course_id/uploads/:upload_id/download', auth.requireLogin, auth.requireCourseMember, uploadCtrl.downloadUpload);
router.post('/courses/:course_id/uploads/:upload_id/delete', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, uploadCtrl.deleteUpload);


// Materials (ZIP upload/extract, list files, download zip)
router.get('/courses/:course_id/materials/upload-zip', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, materialsCtrl.uploadZipForm);
router.post('/courses/:course_id/materials/upload-zip', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, materialsCtrl.uploader.single('zip'), materialsCtrl.uploadZipExtract);
router.get('/courses/:course_id/materials/files', auth.requireLogin, auth.requireCourseMember, materialsCtrl.listMaterialsFiles);
router.get('/courses/:course_id/materials/download-zip', auth.requireLogin, auth.requireCourseMember, materialsCtrl.downloadMaterialsZip);

// CSV helpers
router.get('/courses/:course_id/members/export.csv', auth.requireLogin, auth.requireCourseMember, csvCtrl.exportMembersCsv);
router.get('/courses/:course_id/members/import', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, csvCtrl.importMembersForm);
router.post('/courses/:course_id/members/import', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, csvCtrl.uploader.single('csv'), csvCtrl.importMembersCsv);
router.get('/courses/:course_id/assignments/:assignment_id/grades/export.csv', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, csvCtrl.exportAssignmentGradesCsv);

// K) Question bank
router.get('/courses/:course_id/questions', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, questionCtrl.listQuestions);
router.get('/courses/:course_id/questions/new', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, questionCtrl.newQuestionForm);
router.post('/courses/:course_id/questions', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, questionCtrl.createQuestion);
router.get('/courses/:course_id/questions/:question_id', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, questionCtrl.getQuestion);
router.get('/courses/:course_id/questions/:question_id/edit', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, questionCtrl.editQuestionForm);
router.post('/courses/:course_id/questions/:question_id', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, questionCtrl.updateQuestion);
router.post('/courses/:course_id/questions/:question_id/delete', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, questionCtrl.deleteQuestion);

// L) Quiz
router.get('/courses/:course_id/quizzes', auth.requireLogin, auth.requireCourseMember, quizCtrl.listQuizzes);
router.get('/courses/:course_id/quizzes/new', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, quizCtrl.newQuizForm);
router.post('/courses/:course_id/quizzes', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, quizCtrl.createQuiz);
router.get('/courses/:course_id/quizzes/:quiz_id', auth.requireLogin, auth.requireCourseMember, quizCtrl.getQuiz);
router.get('/courses/:course_id/quizzes/:quiz_id/edit', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, quizCtrl.editQuizForm);
router.post('/courses/:course_id/quizzes/:quiz_id', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, quizCtrl.updateQuiz);
router.post('/courses/:course_id/quizzes/:quiz_id/delete', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, quizCtrl.deleteQuiz);
router.get('/courses/:course_id/quizzes/:quiz_id/questions', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, quizCtrl.configureQuizQuestionsForm);
router.post('/courses/:course_id/quizzes/:quiz_id/questions', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, quizCtrl.configureQuizQuestions);
router.post('/courses/:course_id/quizzes/:quiz_id/questions/:question_id/delete', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, quizCtrl.removeQuizQuestion);

// M) Student take quiz
router.post('/courses/:course_id/quizzes/:quiz_id/start', auth.requireLogin, auth.requireCourseMember, auth.requireStudent, takeQuizCtrl.startAttempt);
router.get('/courses/:course_id/quizzes/:quiz_id/attempts/:attempt_id', auth.requireLogin, auth.requireCourseMember, auth.requireStudent, takeQuizCtrl.attemptPage);
router.post('/courses/:course_id/quizzes/:quiz_id/attempts/:attempt_id/answers', auth.requireLogin, auth.requireCourseMember, auth.requireStudent, takeQuizCtrl.answerQuestion);
router.post('/courses/:course_id/quizzes/:quiz_id/attempts/:attempt_id/submit', auth.requireLogin, auth.requireCourseMember, auth.requireStudent, takeQuizCtrl.submitAttempt);
router.get('/my/quizzes', auth.requireLogin, takeQuizCtrl.viewMyAttempts);

// audit
router.get('/admin/audit', auth.requireLogin, auth.requireAdmin, auditController.getAdminAuditPage);


// Invite links (crypto)
router.post(
  '/courses/:course_id/invites',
  auth.requireLogin,
  auth.requireCourseMember,
  auth.requireCourseStaff,
  inviteCtrl.createCourseInvite
);
router.get('/join', auth.requireLogin, inviteCtrl.joinWithToken);
router.post('/join', auth.requireLogin, inviteCtrl.joinWithToken);

module.exports = router;