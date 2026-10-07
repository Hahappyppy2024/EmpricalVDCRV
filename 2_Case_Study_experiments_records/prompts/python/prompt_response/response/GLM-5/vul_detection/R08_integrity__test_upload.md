4.0 VulType

VulType: R01



4.1 RelatedFiles

\- MISSING: Route handler for POST /api/courses/<course\_id>/uploads

\- MISSING: File storage utility or logic



4.2 RelevantCodeInsideFiles

File: MISSING

\- Needed: Logic processing the uploaded file and determining the storage path



4.3 RootCause

\- The file upload logic fails to sanitize the user-provided filename, allowing directory traversal sequences (../) to be passed to the file system or database.

\- The storage path is constructed using the raw filename without validating or resolving the absolute path, potentially allowing files to be written outside the designated upload folder.



4.4 ActionablePlan

\- Target File: routes/upload\_routes.py (or controller)

&nbsp; Target: Upload handler function

&nbsp; Change: Sanitize the filename using `os.path.basename` (or equivalent) to strip directory information. When saving, generate a safe storage path (e.g., using a UUID or timestamp) or strictly validate that the resolved path is within the allowed `UPLOAD\_FOLDER`.



4.5 FileToActionMap

\- routes/upload\_routes.py → Implement filename sanitization and path validation.

