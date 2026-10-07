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
const uploadCtrl = require('../controllers/uploadController');
const materialsCtrl = require('../controllers/materialsController');
const csvCtrl = require('../controllers/csvController');
const questionCtrl = require('../controllers/questionController');
const quizCtrl = require('../controllers/quizController');
const takeQuizCtrl = require('../controllers/takeQuizController');
const assignmentExportCtrl = require('../controllers/assignmentExportController');
const auditController = require('../controllers/auditController');
const inviteController = require('../controllers/inviteController');
const submissionZipCtrl=require('../controllers/submissionZipController');

console.log('zip ctrl keys =', Object.keys(submissionZipCtrl));
console.log('typeof unzipSubmission =', typeof submissionCtrl.unzipSubmission);
console.log('typeof listExtractedFiles =', typeof submissionCtrl.listExtractedFiles);

// B) Auth / User
router.post('/auth/register', authCtrl.register);
router.post('/auth/login', authCtrl.login);
router.post('/auth/logout', auth.requireLogin, authCtrl.logout);
router.get('/auth/me', auth.requireLogin, authCtrl.me);

// Users list
router.get('/users', auth.requireLogin, userCtrl.listUsers);

// Courses
router.post('/courses', auth.requireLogin, courseCtrl.createCourse);
router.get('/courses', auth.requireLogin, courseCtrl.listCourses);
router.get('/courses/:course_id', auth.requireLogin, auth.requireCourseMember, courseCtrl.getCourse);
router.put('/courses/:course_id', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, courseCtrl.updateCourse);
router.delete('/courses/:course_id', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, courseCtrl.deleteCourse);

// Membership
router.post('/courses/:course_id/members', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, membershipCtrl.addMember);
router.get('/courses/:course_id/members', auth.requireLogin, auth.requireCourseMember, membershipCtrl.listMembers);
router.put('/courses/:course_id/members/:membership_id', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, membershipCtrl.updateMemberRole);
router.delete('/courses/:course_id/members/:membership_id', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, membershipCtrl.removeMember);
router.get('/memberships', auth.requireLogin, membershipCtrl.myMemberships);

// Posts
router.post('/courses/:course_id/posts', auth.requireLogin, auth.requireCourseMember, postCtrl.createPost);
router.get('/courses/:course_id/posts', auth.requireLogin, auth.requireCourseMember, postCtrl.listPosts);
router.get('/courses/:course_id/posts/:post_id', auth.requireLogin, auth.requireCourseMember, postCtrl.getPost);
router.put('/courses/:course_id/posts/:post_id', auth.requireLogin, auth.requireCourseMember, postCtrl.updatePost);
router.delete('/courses/:course_id/posts/:post_id', auth.requireLogin, auth.requireCourseMember, postCtrl.deletePost);

// Comments
router.post('/courses/:course_id/posts/:post_id/comments', auth.requireLogin, auth.requireCourseMember, commentCtrl.createComment);
router.get('/courses/:course_id/posts/:post_id/comments', auth.requireLogin, auth.requireCourseMember, commentCtrl.listComments);
router.put('/courses/:course_id/posts/:post_id/comments/:comment_id', auth.requireLogin, auth.requireCourseMember, commentCtrl.updateComment);
router.delete('/courses/:course_id/posts/:post_id/comments/:comment_id', auth.requireLogin, auth.requireCourseMember, commentCtrl.deleteComment);

// Search
router.get('/courses/:course_id/search/posts', auth.requireLogin, auth.requireCourseMember, searchCtrl.searchPosts);
router.get('/courses/:course_id/search/comments', auth.requireLogin, auth.requireCourseMember, searchCtrl.searchComments);

// Assignments
router.post('/courses/:course_id/assignments', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, assignmentCtrl.createAssignment);
router.get('/courses/:course_id/assignments', auth.requireLogin, auth.requireCourseMember, assignmentCtrl.listAssignments);
router.get('/courses/:course_id/assignments/:assignment_id', auth.requireLogin, auth.requireCourseMember, assignmentCtrl.getAssignment);
router.put('/courses/:course_id/assignments/:assignment_id', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, assignmentCtrl.updateAssignment);
router.delete('/courses/:course_id/assignments/:assignment_id', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, assignmentCtrl.deleteAssignment);

// Submissions
router.post('/courses/:course_id/assignments/:assignment_id/submissions', auth.requireLogin, auth.requireCourseMember, auth.requireStudent, submissionCtrl.createSubmission);
router.put('/courses/:course_id/assignments/:assignment_id/submissions/:submission_id', auth.requireLogin, auth.requireCourseMember, auth.requireStudent, submissionCtrl.updateMySubmission);
router.get('/my/submissions', auth.requireLogin, submissionCtrl.listMySubmissions);
router.get('/courses/:course_id/assignments/:assignment_id/submissions', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, submissionCtrl.listForAssignment);

// Uploads
router.post('/courses/:course_id/uploads', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, uploadCtrl.uploader.single('file'), uploadCtrl.uploadFile);
router.get('/courses/:course_id/uploads', auth.requireLogin, auth.requireCourseMember, uploadCtrl.listUploads);
router.get('/courses/:course_id/uploads/:upload_id/download', auth.requireLogin, auth.requireCourseMember, uploadCtrl.downloadUpload);
router.delete('/courses/:course_id/uploads/:upload_id', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, uploadCtrl.deleteUpload);

// Questions
router.post('/courses/:course_id/questions', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, questionCtrl.createQuestion);
router.get('/courses/:course_id/questions', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, questionCtrl.listQuestions);
router.get('/courses/:course_id/questions/:question_id', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, questionCtrl.getQuestion);
router.put('/courses/:course_id/questions/:question_id', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, questionCtrl.updateQuestion);
router.delete('/courses/:course_id/questions/:question_id', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, questionCtrl.deleteQuestion);

// Quizzes
router.post('/courses/:course_id/quizzes', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, quizCtrl.createQuiz);
router.get('/courses/:course_id/quizzes', auth.requireLogin, auth.requireCourseMember, quizCtrl.listQuizzes);
router.get('/courses/:course_id/quizzes/:quiz_id', auth.requireLogin, auth.requireCourseMember, quizCtrl.getQuiz);
router.put('/courses/:course_id/quizzes/:quiz_id', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, quizCtrl.updateQuiz);
router.delete('/courses/:course_id/quizzes/:quiz_id', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, quizCtrl.deleteQuiz);
router.post('/courses/:course_id/quizzes/:quiz_id/questions', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, quizCtrl.configureQuizQuestions);
router.delete('/courses/:course_id/quizzes/:quiz_id/questions/:question_id', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, quizCtrl.removeQuizQuestion);

// Student take quiz
router.post('/courses/:course_id/quizzes/:quiz_id/attempts/start', auth.requireLogin, auth.requireCourseMember, auth.requireStudent, takeQuizCtrl.startAttempt);
router.post('/courses/:course_id/quizzes/:quiz_id/attempts/:attempt_id/answers', auth.requireLogin, auth.requireCourseMember, auth.requireStudent, takeQuizCtrl.answerQuestion);
router.post('/courses/:course_id/quizzes/:quiz_id/attempts/:attempt_id/submit', auth.requireLogin, auth.requireCourseMember, auth.requireStudent, takeQuizCtrl.submitAttempt);
router.get('/my/quizzes/attempts', auth.requireLogin, takeQuizCtrl.viewMyAttempts);


// Materials (ZIP upload/extract, list files, download zip)
router.post('/courses/:course_id/materials/upload-zip', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, materialsCtrl.uploader.single('zip'), materialsCtrl.uploadZipExtract);
router.get('/courses/:course_id/materials/files', auth.requireLogin, auth.requireCourseMember, materialsCtrl.listMaterialsFiles);
router.get('/courses/:course_id/materials/download-zip', auth.requireLogin, auth.requireCourseMember, materialsCtrl.downloadMaterialsZip);

// CSV helpers
router.get('/courses/:course_id/members/export.csv', auth.requireLogin, auth.requireCourseMember, csvCtrl.exportMembersCsv);
router.post('/courses/:course_id/members/import', auth.requireLogin, auth.requireCourseMember, auth.requireTeacherOrAdmin, csvCtrl.uploader.single('csv'), csvCtrl.importMembersCsv);
router.get('/courses/:course_id/assignments/:assignment_id/grades/export.csv', auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff, csvCtrl.exportAssignmentGradesCsv);


router.post(
  '/courses/:course_id/assignments/:assignment_id/submissions/:submission_id/unzip',
  auth.requireAdmin, // 或 requireLogin + requireCourseStaff
  submissionZipCtrl.unzipSubmissionAttachment
);

router.get(
  '/courses/:course_id/assignments/:assignment_id/submissions/:submission_id/files',
  auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff,
  submissionZipCtrl.listSubmissionExtractedFiles
);


router.get(
  '/courses/:course_id/assignments/:assignment_id/submissions/:submission_id/files',
  auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff,
  submissionZipCtrl.listSubmissionExtractedFiles
);


router.post(
  '/courses/:course_id/assignments/:assignment_id/submissions/export',
  auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff,
  assignmentExportCtrl.exportAssignmentSubmissionsZip
);

router.get(
  '/courses/:course_id/assignments/:assignment_id/submissions/export/:export_id/download',
  auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff,
  assignmentExportCtrl.downloadExportedSubmissionsZip
);

router.post(
  '/courses/:course_id/assignments/:assignment_id/grades/import',
  auth.requireLogin, auth.requireCourseMember, auth.requireCourseStaff,
  csvCtrl.uploader.single('csv'),
  csvCtrl.importAssignmentGradesCsv
);



// course invite
router.post(
  '/courses/:course_id/invites',
  auth.requireLogin,
  auth.requireCourseMember,
  auth.requireCourseStaff,
  inviteController.createCourseInvite
);

// join
router.post(
  '/join',
  auth.requireLogin,
  inviteController.joinWithToken
);

// audit
router.get('/admin/audit', auth.requireAdmin, auditController.getAdminAuditApi);


module.exports = router;
