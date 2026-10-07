4.0 VulType
VulType: R03

4.1 RelatedFiles
- MISSING: Route handler for GET /api/courses/:course_id/search/posts
- MISSING: Database query logic for post search

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Search logic handling the 'keyword' query parameter

4.3 RootCause
- The application constructs SQL queries by concatenating the user-supplied 'keyword' parameter, making it vulnerable to SQL Injection.
- The application lacks proper error handling, causing raw database errors (e.g., SQLite syntax errors) or stack traces to be returned in the response body when a malformed query is executed.

4.4 ActionablePlan
- Target File: controllers/searchController.js (or relevant controller)
  Target: Post search function
  Change: Replace raw SQL concatenation with parameterized queries (prepared statements) to safely handle the 'keyword' input.

- Target File: app.js (or error handling middleware)
  Target: Global error handler
  Change: Implement a global error handling middleware that catches unhandled exceptions and returns a generic error response (e.g., 500 Internal Server Error) without exposing stack traces or database error details.

4.5 FileToActionMap
- controllers/searchController.js → Fix SQL injection via parameterized queries.
- app.js → Add generic error handling to prevent information leakage.