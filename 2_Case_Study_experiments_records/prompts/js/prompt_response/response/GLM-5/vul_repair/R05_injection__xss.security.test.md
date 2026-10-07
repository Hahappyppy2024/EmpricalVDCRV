[1] Patch
```diff
--- a/views/postDetail.ejs
+++ b/views/postDetail.ejs
@@ -1,3 +1,3 @@
 <h1><%= post.title %></h1>
 <div class="post-body">
-  <%- post.body %>
+  <%= post.body %>
 </div>
```

[2] Evidence Mapping
- Signal: `expect(html.text).not.toContain(payload)` fails -> Location: `views/postDetail.ejs` -> Fix: Change unescaped output tag `<%-` to escaped tag `<%=`.
- Signal: `expect(html.text).toMatch(/&lt;script&gt;/)` fails -> Location: `views/postDetail.ejs` -> Fix: Ensure template engine escapes HTML special characters by default.
- Signal: Raw `<script>` visible in `html.text` -> Location: `server.js` or template file -> Fix: Enable auto-escaping or use explicit escaping syntax.

[3] FailureModeGuess
failure_mode_guess: "NotFixed"
failure_mode_guess_reason: The view template explicitly uses the unescaped output syntax (`<%- ... %>`) for a user-controlled field, bypassing default protections.