4.0 VulType
VulType: R03

4.1 RelatedFiles
- MISSING: Route handler for GET /courses/:course_id/posts/:post_id (HTML rendering)
- MISSING: Template file or view engine logic for rendering post details

4.2 RelevantCodeInsideFiles
File: MISSING
- Needed: Template or view code responsible for rendering the 'body' of the post

4.3 RootCause
- The server-side rendering logic outputs the post content (body) raw (unescaped) into the HTML response.
- The template engine's auto-escaping feature is disabled or overridden, allowing the injected `<script>` tag to be interpreted by the browser.

4.4 ActionablePlan
- Target File: views/postDetail.html (or relevant template file)
  Target: The variable output for the post body
  Change: Apply HTML escaping filter/function to the post body variable (e.g., `{{body}}` in Handlebars or `<%= body %>` in EJS with escaping enabled) to convert `<` to `&lt;` and `>` to `&gt;`.

- Target File: server.js (or app configuration file)
  Target: View engine configuration
  Change: Ensure the view engine is configured with auto-escaping enabled by default.

4.5 FileToActionMap
- views/postDetail.html → Enable HTML escaping for post content variables.
- server.js → Verify/enable auto-escaping in view engine settings.