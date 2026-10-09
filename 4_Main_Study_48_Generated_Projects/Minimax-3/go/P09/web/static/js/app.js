document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('[data-dismiss-flash]').forEach(function(el) {
    el.addEventListener('click', function() {
      var parent = el.closest('.flash');
      if (parent) parent.remove();
    });
  });

  document.querySelectorAll('form[data-confirm]').forEach(function(form) {
    form.addEventListener('submit', function(ev) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        ev.preventDefault();
      }
    });
  });

  document.querySelectorAll('[data-api-error-report]').forEach(function(form) {
    form.addEventListener('submit', function(ev) {
      ev.preventDefault();
      var kind = form.getAttribute('data-kind') || 'validation';
      var msg = form.getAttribute('data-message') || 'Unknown error';
      fetch('/api/issue/frontend_api_integration_and_errors', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': form.dataset.csrf || ''},
        body: JSON.stringify({kind: kind, message: msg})
      }).catch(function(err) {
        console.error('Error reporting failed', err);
      });
    });
  });

  window.addEventListener('error', function(event) {
    try {
      fetch('/api/issue/frontend_api_integration_and_errors', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({kind: 'javascript', message: event.message || 'uncaught error', context: (event.filename || '') + ':' + (event.lineno || '')})
      });
    } catch (e) {}
  });

  window.addEventListener('unhandledrejection', function(event) {
    try {
      fetch('/api/issue/frontend_api_integration_and_errors', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({kind: 'promise', message: (event.reason && event.reason.message) || 'unhandled rejection', context: String(event.reason)})
      });
    } catch (e) {}
  });
});