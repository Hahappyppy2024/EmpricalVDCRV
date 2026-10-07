### 4.0 VulType

VulType: R05

---

### 4.1 RelatedFiles

RelatedFiles:

* MISSING: API route/controller for `POST /api/courses/:course_id/posts` (stores post body)
* MISSING: Web (HTML) route/controller for `GET /courses/:course_id/posts/:post_id` (renders post detail page)
* MISSING: Template/view file for post detail rendering (server-rendered HTML)
* MISSING: Data access layer for fetching a post’s title/body for HTML rendering (posts repository/model)

---

### 4.2 RelevantCodeInsideFiles

RelevantCodeInsideFiles:
File: MISSING

* Needed: handler for `POST /api/courses/:course_id/posts` to confirm post body is stored “as-is” (the payload `<script>...</script>` ends up persisted)

File: MISSING

* Needed: handler for `GET /courses/:course_id/posts/:post_id` that loads the post record and passes `post.body` into a template

File: MISSING

* Needed: the post detail template code where the body is inserted into HTML (e.g., something like `<div>${post.body}</div>` or template syntax that disables escaping)

File: MISSING

* Needed: DB query for fetching the post record used in the HTML page (so we can see if it returns raw body and where escaping should occur)

---

### 4.3 RootCause

RootCause:

* The HTML post detail page renders user-controlled `post.body` **without HTML escaping**, so the stored payload `<script>alert("x")</script>` appears raw in the response body.
* The template likely uses an “unescaped/raw HTML” output mode (e.g., EJS `<%- ... %>`, Handlebars triple-stash `{{{...}}}`, or manual string concatenation), bypassing default escaping.
* Enforcement must occur at the **server-rendered HTML boundary** (template rendering / view-model preparation), ensuring `post.body` is escaped before being included in the HTML response.

---

### 4.4 ActionablePlan

ActionablePlan:

* Target File: **HTML route/controller for post detail** (MISSING)
  Target: `GET /courses/:course_id/posts/:post_id`
  Change: Ensure the value passed into the template for the post body is HTML-escaped (either escape in controller before rendering, or rely on template default escaping by avoiding any “raw HTML” render path).

* Target File: **Post detail template/view** (MISSING)
  Target: the template expression that outputs `post.body`
  Change: Replace any raw/unescaped output syntax with escaped output syntax (i.e., do not use raw HTML insertion). The resulting HTML must contain `&lt;script&gt;` rather than `<script>`.

* Target File: **Shared HTML escaping utility** (optional but minimal) (MISSING if not present)
  Target: helper used by templates/controllers
  Change: Add/ensure a standard HTML escape routine (escapes `& < > " '`) and use it consistently for user-generated fields rendered into HTML.

---

### 4.5 FileToActionMap

FileToActionMap:

* MISSING: web route/controller for `/courses/:course_id/posts/:post_id` → escape post body before rendering the template (or ensure template auto-escapes)
* MISSING: post detail template/view → stop rendering `post.body` as raw HTML; output escaped text so `<script>` becomes `&lt;script&gt;`
* MISSING: optional escape helper → provide reusable HTML escaping for user-controlled fields rendered server-side
