// Tiny client helpers used across pages.
(function () {
  document.querySelectorAll('form.inline-form button[type="submit"]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      const form = btn.closest('form');
      if (!form) return;
      if (!form.dataset.confirmed && btn.textContent.toLowerCase().indexOf('delete') !== -1) {
        if (!window.confirm('Delete this record?')) {
          e.preventDefault();
        } else {
          form.dataset.confirmed = '1';
        }
      }
    });
  });

  document.querySelectorAll('details').forEach(function (d) {
    if (window.location.hash && d.querySelector(window.location.hash)) {
      d.open = true;
    }
  });
})();