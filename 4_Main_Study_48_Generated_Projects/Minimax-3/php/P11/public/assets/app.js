// AetherPanel — minimal client-side helpers
(function () {
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });
  // Auto-dismiss flash after 5s
  setTimeout(function () {
    document.querySelectorAll('.flash').forEach(function (el) {
      el.style.transition = 'opacity .4s';
      el.style.opacity = 0;
      setTimeout(function () { el.remove(); }, 500);
    });
  }, 5000);
})();