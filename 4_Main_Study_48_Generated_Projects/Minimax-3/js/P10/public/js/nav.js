// Renders a top navigation bar that requires an authenticated user.
export async function mountNav(activePath) {
  const user = await AI.ensureAuth('/');
  const navHtml = `
    <header class="app-header">
      <h1>AI Assistant / LLM WebUI</h1>
      <div class="flex">
        <span class="muted">${user.display_name} (${user.role})</span>
        <button class="secondary" id="sign-out-btn">Sign out</button>
      </div>
    </header>
    <nav class="main-nav">
      <a href="/dashboard.html">Dashboard</a>
      <a href="/chat.html">Chat</a>
      <a href="/conversations.html">Conversations</a>
      <a href="/templates.html">Templates</a>
      <a href="/models.html">Models</a>
      <a href="/knowledge.html">Knowledge</a>
      <a href="/collections.html">Collections</a>
      <a href="/plugins.html">Plugins</a>
      <a href="/api-keys.html">API Keys</a>
      <a href="/shares.html">Shares</a>
      <a href="/audit.html">Usage & Audit</a>
      ${user.role === 'admin' ? '<a href="/admin.html">Admin</a>' : ''}
    </nav>
  `;
  const headerHost = document.createElement('div');
  headerHost.innerHTML = navHtml;
  document.body.prepend(headerHost);
  AI.bindNav();
  document.getElementById('sign-out-btn').addEventListener('click', () => AI.signOut());
  return user;
}
