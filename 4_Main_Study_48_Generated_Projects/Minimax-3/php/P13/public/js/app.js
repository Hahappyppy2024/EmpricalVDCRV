(function () {
  document.querySelectorAll('.api-call-form').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var out = form.querySelector('.apiresult');
      out.textContent = 'working...';
      var method = (form.getAttribute('data-method') || 'POST').toUpperCase();
      var url = form.getAttribute('action');
      var body = new FormData(form);
      if (method === 'GET' || method === 'HEAD') {
        var qs = new URLSearchParams();
        body.forEach(function (v, k) { qs.append(k, v); });
        var sep = url.indexOf('?') === -1 ? '?' : '&';
        url = url + sep + qs.toString();
        fetch(url, { method: method, credentials: 'same-origin' })
          .then(handle(out))
          .catch(showError(out));
        return;
      }
      fetch(url, { method: method, body: body, credentials: 'same-origin' })
        .then(handle(out))
        .catch(showError(out));
    });
  });
  function handle(out) {
    return function (resp) {
      var ct = resp.headers.get('content-type') || '';
      if (ct.indexOf('application/json') !== -1) {
        return resp.json().then(function (data) { out.textContent = JSON.stringify(data, null, 2); });
      }
      return resp.text().then(function (data) { out.textContent = data; });
    };
  }
  function showError(out) {
    return function (err) { out.textContent = 'Error: ' + err.message; };
  }
})();