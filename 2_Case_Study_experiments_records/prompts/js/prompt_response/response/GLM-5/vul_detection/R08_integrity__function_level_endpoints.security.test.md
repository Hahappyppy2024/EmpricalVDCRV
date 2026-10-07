4.0 VulType
VulType: R08

4.1 RelatedFiles
- MISSING: Route handler for POST /api/courses/:course_id/assignments/:assignment_id/grades/import
- MISSING: Controller or service logic for processing grade CSV imports

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Logic that parses the CSV file and updates submission grades

4.3 RootCause
- The grade import endpoint lacks server-side validation to ensure the 'score' field is a valid numeric value before processing the update.
- The system attempts to update the submission record with the invalid input (or interprets it incorrectly) instead of rejecting the row, resulting in `updatedCount > 0`.

4.4 ActionablePlan
- Target File: controllers/gradeController.js (or assignmentController.js)
  Target: Grade import handler function
  Change: Add strict validation logic for each row in the CSV. Check if `score` is a valid number (e.g., `!isNaN(parseFloat(score))`). If validation fails, skip the record update (do not save) so that `updatedCount` remains 0 for invalid inputs.

- Target File: routes/assignments.js
  Target: Grade import route definition
  Change: Ensure the route directs requests to the validated controller logic (no changes needed if logic is internal to controller).

4.5 FileToActionMap
- controllers/gradeController.js → Add numeric validation for the score field in the import loop.