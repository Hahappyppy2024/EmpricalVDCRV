// Vanilla JS client utility for the conference review system
(function () {
  'use strict';

  // Add a global click handler for auto-acknowledging flash banners
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.flash').forEach(function (el) {
      el.style.cursor = 'pointer';
      el.addEventListener('click', function () { el.style.display = 'none'; });
    });

    // Patch method=DELETE/PUT forms to use POST + override
    document.querySelectorAll('form[data-method]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var m = form.getAttribute('data-method');
        var fd = new FormData(form);
        fetch(form.action, { method: 'POST', body: fd, headers: { 'X-HTTP-Method-Override': m } })
          .then(function (r) { return r.text(); })
          .then(function () { location.reload(); });
      });
    });
  });
})();