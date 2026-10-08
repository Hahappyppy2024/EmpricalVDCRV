export function sanitizeHtml(html) {
  let s = String(html || '');
  s = s.replace(/<\s*script[\s\S]*?<\s*\/\s*script\s*>/gi, '');
  s = s.replace(/<\s*script\b[^>]*>/gi, '');
  s = s.replace(/\s+on\w+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '');
  s = s.replace(/\s+style\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '');
  s = s.replace(/\s+formaction\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '');
  s = s.replace(/(href|src)\s*=\s*["']?\s*javascript:[^"' >]*/gi, '$1="#"');
  s = s.replace(/<(iframe|object|embed|form|input|button|textarea|select|option)[\s\S]*?>/gi, '');
  s = s.replace(/<\/(iframe|object|embed|form|input|button|textarea|select|option)>/gi, '');
  return s;
}

export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
