// Login / signup form behaviour.
(function () {
  const form = document.getElementById('auth-form');
  if (!form) return;
  const statusNode = form.querySelector('[data-role="status"]');

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    const data = {};
    new FormData(form).forEach(function (value, key) { data[key] = value; });
    const nextUrl = form.dataset.next || '/dashboard';
    try {
      const response = await App.api.post('/api/data/account_access', data);
      App.status(statusNode, 'Signed in. Redirecting...', 'success');
      window.location.href = nextUrl;
    } catch (err) {
      App.status(statusNode, err.message, 'error');
    }
  });
})();
