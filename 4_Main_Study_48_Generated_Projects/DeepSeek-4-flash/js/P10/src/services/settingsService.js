const DEFAULTS = {
  blocked_terms: ['ignore previous instructions', 'exfiltrate', 'reveal system prompt'],
  default_model: 'gpt-4o-mini',
  default_provider: 'openai',
  allow_registration: true,
  model_access: ['gpt-4o-mini', 'gpt-4o', 'claude-3-5-sonnet'],
  max_upload_size: 5 * 1024 * 1024,
};

export function createSettingsService(db) {
  const getRow = db.prepare('SELECT setting_value FROM admin_settings WHERE setting_key = ?');
  const upsert = db.prepare(
    `INSERT INTO admin_settings (setting_key, setting_value, updated_by, updated_at)
     VALUES (?, ?, ?, datetime('now'))
     ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_by = excluded.updated_by, updated_at = excluded.updated_at`
  );
  const all = db.prepare('SELECT setting_key, setting_value, updated_by, updated_at FROM admin_settings ORDER BY id');

  function get(key) {
    const row = getRow.get(key);
    if (!row) return DEFAULTS[key];
    return JSON.parse(row.setting_value);
  }

  function set(key, value, updatedBy) {
    upsert.run(key, JSON.stringify(value), updatedBy ?? null);
    return { key, value };
  }

  return {
    defaults: DEFAULTS,
    get,
    set,
    list() {
      const merged = { ...DEFAULTS };
      for (const row of all.all()) {
        merged[row.setting_key] = JSON.parse(row.setting_value);
      }
      return merged;
    },
    blockedTerms() {
      const terms = get('blocked_terms');
      return Array.isArray(terms) ? terms : [];
    },
    modelAccess() {
      const models = get('model_access');
      return Array.isArray(models) ? models : [];
    },
  };
}
