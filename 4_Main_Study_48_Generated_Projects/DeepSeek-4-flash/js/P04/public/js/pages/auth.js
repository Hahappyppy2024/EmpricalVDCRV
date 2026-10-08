import { api, renderError } from '/js/main.js';

document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const result = document.getElementById('login-result');
      result.innerHTML = '';
      try {
        const data = await api('/api/hotel/account_access', {
          method: 'POST',
          body: JSON.stringify({ action: 'login', email: loginForm.email.value, password: loginForm.password.value }),
        });
        window.location.href = data.redirect || '/account';
      } catch (err) {
        renderError(result, err);
      }
    });
  }

  const registerForm = document.getElementById('register-form');
  if (registerForm) {
    registerForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const result = document.getElementById('register-result');
      result.innerHTML = '';
      try {
        const data = await api('/api/hotel/account_access', {
          method: 'POST',
          body: JSON.stringify({
            action: 'register',
            name: registerForm.name.value,
            email: registerForm.email.value,
            phone: registerForm.phone.value,
            password: registerForm.password.value,
          }),
        });
        window.location.href = data.redirect || '/account';
      } catch (err) {
        renderError(result, err);
      }
    });
  }

  const recoverForm = document.getElementById('recover-form');
  if (recoverForm) {
    recoverForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const result = document.getElementById('recover-result');
      result.innerHTML = '';
      try {
        const data = await api('/api/hotel/account_access', {
          method: 'POST',
          body: JSON.stringify({ action: 'reset', email: recoverForm.email.value }),
        });
        result.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
        if (data.dev_reset_url) {
          result.innerHTML += `<div class="alert alert-info">Development reset link: <a href="${data.dev_reset_url}">open reset page</a></div>`;
        }
      } catch (err) {
        renderError(result, err);
      }
    });
  }

  const resetForm = document.getElementById('reset-form');
  if (resetForm) {
    resetForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const result = document.getElementById('reset-result');
      result.innerHTML = '';
      try {
        const data = await api('/api/hotel/account_access', {
          method: 'POST',
          body: JSON.stringify({ action: 'reset_confirm', token: resetForm.dataset.token, password: resetForm.password.value }),
        });
        result.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
        setTimeout(() => window.location.assign('/account/login'), 1600);
      } catch (err) {
        renderError(result, err);
      }
    });
  }
});
