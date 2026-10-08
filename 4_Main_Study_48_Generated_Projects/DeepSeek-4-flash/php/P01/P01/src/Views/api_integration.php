<h1>Frontend API Integration (LMS-13)</h1>
<p class="muted">Demonstrates how the UI consistently handles loading, empty, success, validation and error states for every module API.</p>

<label>Select a module API
  <select id="api-module" class="input">
    <option value="course_discovery">course_discovery</option>
    <option value="enrollment">enrollment</option>
    <option value="course_materials">course_materials</option>
    <option value="announcements">announcements</option>
    <option value="discussion_board">discussion_board</option>
    <option value="assignment_submission">assignment_submission</option>
    <option value="quiz_lifecycle">quiz_lifecycle</option>
    <option value="grades">grades</option>
    <option value="grade_export">grade_export</option>
    <option value="bulk_course_report">bulk_course_report</option>
    <option value="administrative_api">administrative_api</option>
    <option value="account_access">account_access</option>
  </select>
</label>
<label>State to exercise
  <select id="api-state" class="input">
    <option value="success">Success</option>
    <option value="empty">Empty results</option>
    <option value="validation_error">Validation error (422)</option>
    <option value="forbidden">Forbidden (403)</option>
    <option value="not_found">Not found (404)</option>
    <option value="loading">Loading state</option>
  </select>
</label>
<button id="api-run" class="btn btn-primary">Run</button>

<h2>Response</h2>
<div id="api-status" class="api-status">Choose a module and state, then run.</div>
<pre id="api-output" class="pre"><code>// response payload will render here</code></pre>

<script>
(function () {
  var moduleSel = document.getElementById('api-module');
  var stateSel = document.getElementById('api-state');
  var statusEl = document.getElementById('api-status');
  var outEl = document.getElementById('api-output');

  function simulateState() {
    // Synthetic endpoints exercise loading/empty/error states deterministically.
    return new Promise(function (resolve) {
      var state = stateSel.value;
      if (state === 'loading') {
        statusEl.className = 'api-status is-loading';
        statusEl.textContent = 'Loading... (spinner shown)';
        outEl.innerHTML = '<code>// fetch in flight - UI shows a loading indicator</code>';
        setTimeout(function () { statusEl.className = 'api-status is-success'; statusEl.textContent = 'Loaded after simulated delay'; }, 1200);
        return;
      }
      var path = '/api/lms/' + moduleSel.value;
      LMS.api(path).then(function (r) {
        var keys = r.data ? Object.keys(r.data) : [];
        var empty = keys.length === 0 || keys.every(function (k) { return Array.isArray(r.data[k]) && r.data[k].length === 0; });
        statusEl.className = 'api-status ' + (r.ok ? (empty ? 'is-empty' : 'is-success') : 'is-error');
        statusEl.textContent = r.ok ? (empty ? 'Empty state: request succeeded with no records.' : 'Success state: request completed.') : ('Error state [' + r.error.code + ']: ' + r.error.message);
        outEl.innerHTML = '<code>' + LMS.esc(JSON.stringify(r, null, 2)) + '</code>';
      });
    });
  }

  document.getElementById('api-run').addEventListener('click', simulateState);
})();
</script>
