# P01 — Learning Management System — revised use cases

This project contains 14 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| LMS-01 | Account access | Visitor; student; instructor; admin | POST /api/auth/register; POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| LMS-02 | Course discovery | Visitor; student; instructor | GET /api/courses?q=&category=&instructorId=&enrollmentStatus=; GET /api/courses/{courseId} |
| LMS-03 | Enrollment | Student; instructor; admin | POST /api/courses/{courseId}/enrollments; DELETE /api/courses/{courseId}/enrollments/me; GET /api/courses/{courseId}/enrollments |
| LMS-04 | Course materials | Instructor; student | GET /api/courses/{courseId}/materials; POST /api/courses/{courseId}/materials; PATCH /api/materials/{materialId}; GET /api/materials/{materialId}/content; DELETE /api/materials/{materialId} |
| LMS-05 | Announcements | Instructor; student | GET /api/courses/{courseId}/announcements; POST /api/courses/{courseId}/announcements; PATCH /api/announcements/{announcementId}; DELETE /api/announcements/{announcementId} |
| LMS-06 | Discussion board | Student; instructor | GET /api/courses/{courseId}/topics; POST /api/courses/{courseId}/topics; GET /api/topics/{topicId}/replies; POST /api/topics/{topicId}/replies; PATCH /api/posts/{postId}; DELETE /api/posts/{postId} |
| LMS-07 | Assignments and submissions | Instructor; student | GET /api/courses/{courseId}/assignments; POST /api/courses/{courseId}/assignments; GET /api/assignments/{assignmentId}; POST /api/assignments/{assignmentId}/submissions; GET /api/assignments/{assignmentId}/submissions |
| LMS-08 | Quiz lifecycle | Instructor; student | POST /api/courses/{courseId}/quizzes; PATCH /api/quizzes/{quizId}; POST /api/quizzes/{quizId}/publish; POST /api/quizzes/{quizId}/attempts; PUT /api/quiz-attempts/{attemptId}/answers; POST /api/quiz-attempts/{attemptId}/submit; GET /api/quiz-attempts/{attemptId}/result |
| LMS-09 | Grades and feedback | Instructor; student | GET /api/courses/{courseId}/gradebook/me; GET /api/courses/{courseId}/gradebook; PUT /api/submissions/{submissionId}/grade; PUT /api/quiz-attempts/{attemptId}/grade |
| LMS-10 | Grade export | Instructor; admin | GET /api/courses/{courseId}/gradebook/export?format=csv |
| LMS-11 | Course reporting | Instructor; admin | GET /api/courses/{courseId}/reports/overview; GET /api/admin/reports/courses?from=&to=&status= |
| LMS-12 | Course administration | Instructor; admin | POST /api/courses; PATCH /api/courses/{courseId}; POST /api/courses/{courseId}/instructors; DELETE /api/courses/{courseId}/instructors/{userId}; POST /api/courses/{courseId}/publish; POST /api/courses/{courseId}/archive; GET /api/admin/categories; POST /api/admin/categories |
| LMS-13 | Dashboard and notifications | Student; instructor | GET /api/dashboard; GET /api/notifications?unread=true; POST /api/notifications/{notificationId}/read |
| LMS-14 | Profile and preferences | Student; instructor; admin | GET /api/profile; PATCH /api/profile; GET /api/profile/preferences; PATCH /api/profile/preferences |
