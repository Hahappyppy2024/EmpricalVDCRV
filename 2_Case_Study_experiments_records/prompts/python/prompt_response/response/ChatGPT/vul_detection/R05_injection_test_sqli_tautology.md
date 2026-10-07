\\### 4.0 VulType







VulType: R05







---







\\### 4.1 RelatedFiles







RelatedFiles:







\\\* MISSING: API router that defines `GET /api/courses/<course\\\_id>/posts` (with optional `keyword` query)



\\\* MISSING: Posts controller/handler that implements listing/searching posts and parses `keyword`



\\\* MISSING: Posts repository/data-access code that builds and executes the SQL query (the SQLi injection point)



\\\* MISSING: Global error/response sanitizer for DB errors (to prevent leakage of `sqlite\\\_master`, schema details, or sensitive columns)







---







\\### 4.2 RelevantCodeInsideFiles







RelevantCodeInsideFiles:



File: MISSING







\\\* Needed: route definition for `GET /api/courses/<course\\\_id>/posts` and how it dispatches to a posts list/search handler (including whether `keyword` triggers a different SQL path)







File: MISSING







\\\* Needed: handler logic that reads `keyword` from query string and chooses the DB query (e.g., `if keyword: ... WHERE ... LIKE ...`)







File: MISSING







\\\* Needed: the exact SQL construction/execution for keyword search (look for string interpolation/concatenation such as `... WHERE body LIKE '%{keyword}%'` or `... AND (title LIKE '%" + keyword + "%')`)







File: MISSING







\\\* Needed: response serialization logic (ensure it doesn’t accidentally include sensitive fields like `password\\\_hash` in JSON and doesn’t dump sqlite error details)







---







\\### 4.3 RootCause







RootCause:







\\\* The posts search query likely embeds `keyword` directly into SQL (string concatenation/interpolation), so a tautology payload (`' OR 1=1 --`) alters the WHERE clause and \\\*\\\*broadens results\\\*\\\* (len(injected) > len(baseline)).



\\\* The query path may also be selecting overly-broad columns (e.g., `SELECT \\\*` across joins), risking leakage of sensitive columns in the response body (the test explicitly checks for `password\\\_hash` and `sqlite\\\_master`).



\\\* The missing/incorrect enforcement belongs in the \\\*\\\*repository/DB query layer\\\*\\\* (parameterized queries) and in the \\\*\\\*controller/serializer\\\*\\\* (whitelist fields returned to JSON).







---







\\### 4.4 ActionablePlan







ActionablePlan:







\\\* Target File: \\\*\\\*Posts API route/controller\\\*\\\* (MISSING)



\&nbsp; Target: `GET /api/courses/<course\\\_id>/posts` (keyword search behavior)



\&nbsp; Change: Treat `keyword` as data: validate it as a string (optional length cap), then pass it to the repository as a parameter (do not build SQL in the controller). Ensure the JSON response includes only post fields (e.g., `post\\\_id`, `title`, `body`, `created\\\_at`, `author\\\_display\\\_name`) and never user auth fields.







\\\* Target File: \\\*\\\*Posts repository / DB access module\\\*\\\* (MISSING)



\&nbsp; Target: SQL query that filters posts by `keyword`



\&nbsp; Change: Replace any interpolated SQL with parameterized SQL placeholders; bind `%keyword%` as a parameter for LIKE clauses. Ensure the WHERE clause cannot be altered by quotes/comment markers. This prevents the tautology from expanding results compared to baseline.







\\\* Target File: \\\*\\\*Posts JSON serialization / query column selection\\\*\\\* (MISSING)



\&nbsp; Target: SELECT list for posts query and response mapping



\&nbsp; Change: Avoid `SELECT \\\*` (especially across joins). Select only required post columns; never include `password\\\_hash` (and avoid joining `users` unless needed for display name). This blocks sensitive-field leakage checks.







\\\* Target File: \\\*\\\*Global DB error handler\\\*\\\* (MISSING; defense-in-depth)



\&nbsp; Target: error-to-response mapping for DB exceptions



\&nbsp; Change: On SQL errors, return controlled \\\*\\\*400\\\*\\\* with a generic error message and no schema strings (avoid echoing `sqlite\\\_master`, SQL text, or stack traces).







---







\\### 4.5 FileToActionMap







FileToActionMap:







\\\* MISSING: posts API route/controller → parse/validate `keyword`; call repo with parameter; ensure JSON whitelist (no `password\\\_hash`)



\\\* MISSING: posts repository/DB module → parameterize keyword LIKE query; remove SQL string interpolation to prevent tautology expansion



\\\* MISSING: posts query/serializer → avoid `SELECT \\\*`; select only safe post fields; prevent sensitive DB field exposure



\\\* MISSING: global error handler → sanitize DB errors into generic 400 without leaking sqlite/schema details









