(function () {
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form.matches('form[data-ajax="true"]')) {
      event.preventDefault();
      var formData = new FormData(form);
      var payload = {};
      formData.forEach(function (value, key) { payload[key] = value; });
      var body = JSON.stringify(payload);
      fetch(form.action || window.location.pathname, {
        method: form.method || 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF': formData.get('_csrf') || '' },
        body: body
      }).then(function (r) {
        return r.text();
      }).then(function (text) {
        var notice = document.createElement('div');
        notice.className = 'flash flash-notice';
        notice.textContent = text.slice(0, 1000);
        form.appendChild(notice);
      }).catch(function (err) {
        var notice = document.createElement('div');
        notice.className = 'flash flash-error';
        notice.textContent = 'Request failed: ' + err;
        form.appendChild(notice);
      });
    }
  });

  function bind(id, intent) {
    var form = document.getElementById(id);
    if (!form) return;
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var data = {};
      Array.from(form.querySelectorAll('input,select,textarea')).forEach(function (el) {
        data[el.name] = el.value;
      });
      data.intent = intent;
      fetch('/api/shop/frontend_api_integration', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      }).then(function (r) { return r.text(); })
        .then(function (text) {
          var preId = form.id === 'preCheckForm' ? 'preCheckResult' : 'paymentResult';
          var pre = document.getElementById(preId);
          if (pre) pre.textContent = text;
        });
    });
  }
  bind('preCheckForm', 'validate_form');
  bind('paymentForm', 'validate_payment');

  var snapshotBtn = document.querySelector('a[data-fetch="true"]');
  if (snapshotBtn) {
    snapshotBtn.addEventListener('click', function (event) {
      event.preventDefault();
      fetch(snapshotBtn.getAttribute('href'), { credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (text) {
          var pre = document.getElementById('snapshot');
          if (pre) pre.textContent = text;
        });
      return false;
    });
  }
})();