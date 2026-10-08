import { toast } from './util.js';

export function createRichTextEditor(container, initialHtml = '', opts = {}) {
  const state = { html: initialHtml || '' };

  const wrapper = document.createElement('div');
  wrapper.className = 'rte-wrap';

  const toolbar = document.createElement('div');
  toolbar.className = 'rte-toolbar';

  const commands = [
    { cmd: 'bold', label: 'B' },
    { cmd: 'italic', label: 'I', style: 'font-style: italic' },
    { cmd: 'underline', label: 'U', style: 'text-decoration: underline' },
    { cmd: 'formatBlock', value: 'H2', label: 'H2' },
    { cmd: 'formatBlock', value: 'H3', label: 'H3' },
    { cmd: 'formatBlock', value: 'P', label: '¶' },
    { cmd: 'insertUnorderedList', label: '• List' },
    { cmd: 'insertOrderedList', label: '1. List' },
    { cmd: 'formatBlock', value: 'BLOCKQUOTE', label: '❝ Quote' },
    { cmd: 'createLink', label: '🔗 Link', prompt: 'url' },
    { cmd: 'unlink', label: 'Unlink' }
  ];

  for (const c of commands) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.innerHTML = `<span${c.style ? ` style="${c.style}"` : ''}>${c.label}</span>`;
    btn.addEventListener('click', () => {
      content.focus();
      if (c.cmd === 'createLink') {
        const url = window.prompt('Enter link URL (https://...)');
        if (!url) return;
        if (!/^https?:\/\//i.test(url)) {
          toast('Link must start with http:// or https://', 'error');
          return;
        }
        document.execCommand('createLink', false, url);
      } else if (c.value) {
        document.execCommand(c.cmd, false, c.value);
      } else {
        document.execCommand(c.cmd, false, null);
      }
      state.html = content.innerHTML;
      if (opts.onChange) opts.onChange(state.html);
    });
    toolbar.appendChild(btn);
  }

  if (opts.onInsertMedia) {
    const mediaBtn = document.createElement('button');
    mediaBtn.type = 'button';
    mediaBtn.textContent = '🖼 Insert media';
    mediaBtn.addEventListener('click', () => opts.onInsertMedia((url, alt) => {
      content.focus();
      document.execCommand('insertHTML', false, `<img src="${url}" alt="${(alt || '').replace(/"/g, '&quot;')}" style="max-width:100%">`);
      state.html = content.innerHTML;
      if (opts.onChange) opts.onChange(state.html);
    }));
    toolbar.appendChild(mediaBtn);
  }

  const content = document.createElement('div');
  content.className = 'rte-content';
  content.contentEditable = 'true';
  content.innerHTML = state.html;

  content.addEventListener('input', () => {
    state.html = content.innerHTML;
    if (opts.onChange) opts.onChange(state.html);
  });

  wrapper.appendChild(toolbar);
  wrapper.appendChild(content);
  container.appendChild(wrapper);

  return {
    getHtml: () => content.innerHTML,
    setHtml: (html) => {
      content.innerHTML = html || '';
      state.html = content.innerHTML;
    },
    focus: () => content.focus()
  };
}

export function renderPreview(container, html) {
  container.innerHTML = html || '<p class="muted">Nothing to preview yet.</p>';
}
