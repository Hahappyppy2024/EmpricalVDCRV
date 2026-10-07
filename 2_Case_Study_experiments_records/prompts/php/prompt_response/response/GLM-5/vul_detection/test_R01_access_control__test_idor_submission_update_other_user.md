4.0 VulType

VulType: R01



4.1 RelatedFiles

\- MISSING: Route handler for PUT /api/courses/<course\_id>/assignments/<assignment\_id>/submissions/<submission\_id>

\- MISSING: Controller logic for updating submissions



4.2 RelevantCodeInsideFiles

File: MISSING

\- Needed: Logic to handle submission updates and verify ownership



4.3 RootCause

\- The submission update endpoint processes the request based solely on the `submission\_id` provided in the URL without verifying that the authenticated user is the owner of that submission.

\- It lacks an authorization check to ensure `current\_user.id` matches `submission.user\_id`, allowing a student to overwrite another student's work.



4.4 ActionablePlan

\- Target File: routes/submission\_routes.py (or controller)

&nbsp; Target: PUT endpoint handler for submissions

&nbsp; Change: Before performing the update, fetch the submission and verify that `submission.user\_id` equals the currently authenticated user's ID. If they do not match, return 403 Forbidden.



4.5 FileToActionMap

\- routes/submission\_routes.py → Add ownership verification logic to the update endpoint.

