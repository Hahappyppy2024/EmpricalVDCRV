(function () {
  const logout = document.getElementById('logoutBtn');
  if (logout) {
    logout.addEventListener('click', async () => {
      await fetch('/api/cms/account_access/logout', { method: 'POST' });
      window.location.href = '/';
    });
  }
})();
