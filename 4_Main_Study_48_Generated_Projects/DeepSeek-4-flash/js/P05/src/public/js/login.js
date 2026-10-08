import { api } from './api.js';
import { toast } from './util.js';

const isSignup = window.location.pathname.startsWith('/signup');

function setMode(signup) {
  document.getElementById('auth-title').textContent = signup ? 'Create account' : 'Sign in';
  document.getElementById('auth-sub').textContent = signup ? 'Register to start authoring content.' : 'Access the content management dashboard.';
  document.getElementById('auth-submit').textContent = signup ? 'Create account' : 'Sign in';
  document.getElementById('auth-switch-text').textContent = signup ? 'Already have an account?' : 'No account?';
  document.getElementById('auth-switch').textContent = signup ? 'Sign in' : 'Create one';
  document.getElementById('email-field').style.display = signup ? '' : 'none';
  document.getElementById('username-field').style.display = signup ? '' : 'none';
  document.getElementById('display-field').style.display = signup ? '' : 'none';
  document.getElementById('email').required = signup;
  document.getElementById('username').required = signup;
  document.getElementById('password').autocomplete = signup ? 'new-password' : 'current-password';
}

document.getElementById('auth-switch').addEventListener('click', (e) => {
  e.preventDefault();
  setMode(!document.getElementById('auth-switch').textContent.startsWith('Create'));
});

setMode(isSignup);

document.getElementById('auth-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const errorBox = document.getElementById('auth-errors');
  errorBox.innerHTML = '';
  const signup = document.getElementById('auth-submit').textContent === 'Create account';

  try {
    let result;
    if (signup) {
      result = await api('/api/auth/register', {
        method: 'POST',
        body: {
          username: document.getElementById('username').value,
          email: document.getElementById('email').value,
          password: document.getElementById('password').value,
          displayName: document.getElementById('displayName').value || null
        }
      });
    } else {
      result = await api('/api/auth/login', {
        method: 'POST',
        body: {
          identifier: document.getElementById('identifier').value,
          password: document.getElementById('password').value
        }
      });
    }
    toast(result.message || 'Welcome!', 'success');
    window.location.href = '/dashboard';
  } catch (err) {
    errorBox.innerHTML = `<div class="field-error">${escapeHtml(err.message)}</div>`;
  }
});

function escapeHtml(v) {
  return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
