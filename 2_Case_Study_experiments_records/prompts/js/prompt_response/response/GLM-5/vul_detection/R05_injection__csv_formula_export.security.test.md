4.0 VulType
VulType: R03

4.1 RelatedFiles
- MISSING: Route handler for GET /api/courses/:course_id/members/export.csv (or similar export endpoints)
- MISSING: CSV generation helper/utility function

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Logic that queries member data and formats it into a CSV response

4.3 RootCause
- The CSV export functionality fails to sanitize or escape user-controlled data before writing it to the CSV file.
- Specifically, it does not prefix cell values starting with formula characters (e.g., =, +, -, @) with a single quote ('), allowing CSV injection attacks.

4.4 ActionablePlan
- Target File: controllers/courseController.js (or the file handling the export route)
  Target: CSV export handler function
  Change: Implement output sanitization for CSV fields. If a cell value begins with '=', '+', '-', '@', or '\t', prepend a single quote (') to the value to prevent it from being interpreted as a formula by spreadsheet software.

4.5 FileToActionMap
- controllers/courseController.js → Add sanitization logic to the CSV export output stream.