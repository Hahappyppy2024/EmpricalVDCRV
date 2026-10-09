import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const cache = new Map();

export function render(viewName, params = {}) {
  const file = join(__dirname, 'views', `${viewName}.html`);
  if (!cache.has(file)) {
    cache.set(file, readFileSync(file, 'utf8'));
  }
  let tpl = cache.get(file);
  // IMPORTANT: process control blocks ({{#if}}, {{#each}}) BEFORE leaf variables,
  // otherwise the {{var}} regex would consume {{#if user}} / {{/if}} as keys.
  // {{#if cond}}...{{else}}...{{/if}} (else optional)
  tpl = tpl.replace(
    /\{\{#if\s+([^}]+)\}\}([\s\S]*?)(?:\{\{else\}\}([\s\S]*?))?\{\{\/if\}\}/g,
    (_, key, truthy, falsy) => {
      const v = get(params, key);
      return v ? truthy : (falsy !== undefined ? falsy : '');
    }
  );
  // {{#each arr as item}}...{{/each}}
  tpl = tpl.replace(
    /\{\{#each\s+(\S+)\s+as\s+(\S+)\}\}([\s\S]*?)\{\{\/each\}\}/g,
    (_, arrKey, itemKey, inner) => {
      const arr = get(params, arrKey);
      if (!Array.isArray(arr)) return '';
      return arr.map((it) => {
        let out = inner;
        const re = new RegExp(`\\{\\{${itemKey}\\.(\\w+)\\}\\}`, 'g');
        out = out.replace(re, (_, k) => escapeHtml(it?.[k] != null ? String(it[k]) : ''));
        const reBare = new RegExp(`\\{\\{${itemKey}\\}\\}`, 'g');
        out = out.replace(reBare, () => escapeHtml(it != null ? String(it) : ''));
        return out;
      }).join('');
    }
  );
  // {{{raw}}} keeps content as-is; {{var}} escapes HTML.
  tpl = tpl.replace(/\{\{\{([\s\S]+?)\}\}\}/g, (_, key) => String(get(params, key) ?? ''));
  // {{var}} but only when NOT preceded by { (avoids matching {{{...}}})
  tpl = tpl.replace(/(?<!\{)\{\{([^}]+)\}\}/g, (_, key) => escapeHtml(String(get(params, key) ?? '')));
  return tpl;
}

function get(obj, path) {
  return path.split('.').reduce((o, k) => (o == null ? undefined : o[k]), obj);
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
