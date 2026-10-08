# P02 — Conference Review System — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| CONF-01 | Account access and recovery | Author; reviewer; chair; admin | POST /api/auth/register; POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| CONF-02 | Conference phases | Chair | GET /api/conferences/{conferenceId}/phases; PUT /api/conferences/{conferenceId}/phases; POST /api/conferences/{conferenceId}/phases/{phase}/activate |
| CONF-03 | Paper submission | Author | POST /api/conferences/{conferenceId}/submissions; PATCH /api/submissions/{submissionId}; POST /api/submissions/{submissionId}/manuscript; POST /api/submissions/{submissionId}/withdraw |
| CONF-04 | Submission discovery | Author; reviewer; chair | GET /api/submissions?q=&status=&track=&page=; GET /api/submissions/{submissionId} |
| CONF-05 | Manuscript access | Author; reviewer; chair | GET /api/submissions/{submissionId}/manuscript |
| CONF-06 | Reviewer assignment | Chair | GET /api/submissions/{submissionId}/reviewers; POST /api/submissions/{submissionId}/reviewers; DELETE /api/submissions/{submissionId}/reviewers/{reviewerId}; POST /api/submissions/{submissionId}/conflicts |
| CONF-07 | Reviewing | Reviewer | GET /api/review-assignments/{assignmentId}/review; PUT /api/review-assignments/{assignmentId}/review; POST /api/review-assignments/{assignmentId}/review/submit |
| CONF-08 | Rebuttal | Author; reviewer; chair | GET /api/submissions/{submissionId}/rebuttal; PUT /api/submissions/{submissionId}/rebuttal; POST /api/submissions/{submissionId}/rebuttal/submit |
| CONF-09 | Decision management | Chair | PUT /api/submissions/{submissionId}/decision; POST /api/submissions/{submissionId}/decision/release; GET /api/submissions/{submissionId}/decision |
| CONF-10 | Double-blind identity handling | Author; reviewer; chair | GET /api/submissions/{submissionId}/view; GET /api/reviews/{reviewId}/view |
| CONF-11 | Conference exports | Chair | GET /api/conferences/{conferenceId}/exports/submissions.csv; GET /api/conferences/{conferenceId}/exports/reviews.csv; GET /api/conferences/{conferenceId}/exports/decisions.csv |
| CONF-12 | Notifications and activity | Author; reviewer; chair | GET /api/notifications; POST /api/notifications/{notificationId}/read; GET /api/submissions/{submissionId}/activity |
