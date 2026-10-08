<h1>Error Responses (LMS-14)</h1>
<p class="muted">Stable, generic error responses are returned for every invalid request. They never expose internal stack traces and always use the shape <code>{ok:false, error:{code, message}}</code>.</p>

<label>Trigger an error code
  <select id="error-code" class="input">
    <option value="VALIDATION_ERROR">VALIDATION_ERROR (422)</option>
    <option value="UNAUTHENTICATED">UNAUTHENTICATED (401)</option>
    <option value="FORBIDDEN">FORBIDDEN (403)</option>
    <option value="NOT_FOUND">NOT_FOUND (404)</option>
    <option value="INTERNAL_ERROR">INTERNAL_ERROR (500)</option>
  </select>
</label>
<button id="error-run" class="btn btn-primary">Trigger</button>

<h2>Response</h2>
<pre id="error-output" class="pre"><code>// triggered error payload will render here</code></pre>
<p class="muted small">Try also: an unknown module <code>GET /api/lms/unknown_module</code> or a nonexistent record <code>PATCH /api/lms/grades/999999</code>.</p>

<script>
(function () {
  var codeSel = document.getElementById('error-code');
  document.getElementById('error-run').addEventListener('click', function () {
    var code = codeSel.value;
    LMS.api('/api/lms/error_responses?code=' + code).then(function (r) {
      var out = document.getElementById('error-output');
      out.innerHTML = '<code>' + LMS.esc(JSON.stringify(r, null, 2)) + '</code>';
    });
  });
})();
</script>
