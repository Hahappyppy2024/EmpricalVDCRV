# P15 — Healthcare Appointment & Patient Portal — revised use cases

This project contains 12 business use cases. These specifications define the application to generate; **do not generate functional, integration, browser, security, or other test files**. Acceptance will be performed separately.

| ID | Use case | Actors | API/event contract |
| --- | --- | --- | --- |
| HEALTH-01 | Account access | Patient; clinician; staff; admin | POST /api/auth/register; POST /api/auth/login; POST /api/auth/logout; POST /api/auth/password-reset-requests; POST /api/auth/password-resets |
| HEALTH-02 | Patient profile | Patient; clinician; staff | GET /api/patients/me; PATCH /api/patients/me; GET /api/patients/{patientId} |
| HEALTH-03 | Clinician directory | Patient | GET /api/clinicians?specialty=&location=&q=&page=; GET /api/clinicians/{clinicianId} |
| HEALTH-04 | Appointment booking | Patient; staff | GET /api/clinicians/{clinicianId}/available-slots?from=&to=; POST /api/appointments; PATCH /api/appointments/{appointmentId}; POST /api/appointments/{appointmentId}/cancel |
| HEALTH-05 | Clinician schedule | Clinician; staff | GET /api/clinicians/{clinicianId}/schedule; POST /api/clinicians/{clinicianId}/availability; PATCH /api/availability/{availabilityId}; POST /api/appointments/{appointmentId}/status |
| HEALTH-06 | Visit notes | Clinician | GET /api/appointments/{appointmentId}/notes; POST /api/appointments/{appointmentId}/notes; POST /api/visit-notes/{noteId}/amendments; GET /api/patients/me/visit-summaries |
| HEALTH-07 | Lab results | Patient; clinician; staff | POST /api/patients/{patientId}/lab-results; POST /api/lab-results/{resultId}/release; GET /api/patients/me/lab-results; GET /api/lab-results/{resultId} |
| HEALTH-08 | Secure messages | Patient; clinician; staff | GET /api/health-conversations; POST /api/health-conversations; GET /api/health-conversations/{conversationId}/messages; POST /api/health-conversations/{conversationId}/messages |
| HEALTH-09 | Prescription requests | Patient; clinician | POST /api/prescription-requests; GET /api/prescription-requests; POST /api/prescription-requests/{requestId}/approve; POST /api/prescription-requests/{requestId}/reject |
| HEALTH-10 | Document upload | Patient; staff | POST /api/patients/{patientId}/documents; GET /api/patient-documents/{documentId}/content; DELETE /api/patient-documents/{documentId} |
| HEALTH-11 | Billing and visit summaries | Patient; staff | GET /api/patients/me/billing-statements; GET /api/billing-statements/{statementId}; GET /api/visit-summaries/{summaryId}.pdf |
| HEALTH-12 | Admin operations and audit | Admin | GET /api/admin/users; PATCH /api/admin/users/{userId}/roles; GET /api/admin/audit-events; PATCH /api/admin/clinic-settings |
