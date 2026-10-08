(function () {
  const message = document.getElementById('auth-message');
  const tabs = document.querySelectorAll('.auth-tab');
  const forms = { login: document.getElementById('login-form'), register: document.getElementById('register-form') };

  function show(text, kind) {
    message.textContent = text;
    message.className = 'auth-message ' + (kind || 'error');
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      tabs.forEach((t) => t.classList.toggle('active', t === tab));
      forms.login.classList.toggle('hidden', tab.dataset.tab !== 'login');
      forms.register.classList.toggle('hidden', tab.dataset.tab !== 'register');
      message.textContent = '';
    });
  });

  async function submit(url, payload) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error?.message || 'Request failed');
    return data;
  }

  forms.login.addEventListener('submit', async (e) => {
    e.preventDefault();
    message.textContent = 'Signing in...';
    message.className = 'auth-message info';
    try {
      await submit('/api/auth/login', Object.fromEntries(new FormData(forms.login)));
      window.location.replace('/app');
    } catch (err) {
      show(err.message);
    }
  });

  forms.register.addEventListener('submit', async (e) => {
    e.preventDefault();
    message.textContent = 'Creating account...';
    message.className = 'auth-message info';
    try {
      await submit('/api/auth/register', Object.fromEntries(new FormData(forms.register)));
      window.location.replace('/app');
    } catch (err) {
      show(err.message);
    }
  });
})();
