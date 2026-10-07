4.0 VulType

VulType: R03



4.1 RelatedFiles

\- MISSING: Route handler for GET /api/courses/<course\_id>/search/posts

\- MISSING: Database query logic for post search



4.2 RelevantCodeInsideFiles

File: MISSING

\- Needed: Logic handling the 'keyword' query parameter and executing the database search



4.3 RootCause

\- The search endpoint constructs SQL queries using string concatenation or f-strings with the user-supplied `keyword` parameter.

\- This allows an attacker to input SQL control characters (like `' OR 1=1 --`) to alter the query logic, resulting in the return of all records instead of just the matching ones.



4.4 ActionablePlan

\- Target File: routes/posts.py (or search.py)

&nbsp; Target: Search posts handler function

&nbsp; Change: Replace dynamic SQL string construction with parameterized queries (prepared statements). For example, change `f"WHERE title LIKE '%{keyword}%'"` to `WHERE title LIKE ?` with parameters `\['%' + keyword + '%']`.



4.5 FileToActionMap

\- routes/posts.py → Refactor search query to use parameterized inputs.

