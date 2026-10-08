const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

export function validate(body, rules) {
  const errors = {};
  for (const [field, rule] of Object.entries(rules)) {
    const value = body ? body[field] : undefined;
    const label = rule.label || field;

    if (value === undefined || value === null || value === '') {
      if (rule.required) {
        errors[field] = `${label} is required.`;
      }
      continue;
    }

    if (rule.type === 'string') {
      if (typeof value !== 'string') {
        errors[field] = `${label} must be a string.`;
        continue;
      }
      if (rule.trim === false) {
        // keep as-is
      } else if (typeof value.trim === 'function' && value.trim().length === 0) {
        errors[field] = `${label} cannot be blank.`;
        continue;
      }
      if (rule.minLength && value.length < rule.minLength) {
        errors[field] = `${label} must be at least ${rule.minLength} characters.`;
      }
      if (rule.maxLength && value.length > rule.maxLength) {
        errors[field] = `${label} must be at most ${rule.maxLength} characters.`;
      }
      if (rule.pattern && !rule.pattern.test(value)) {
        errors[field] = rule.message || `${label} has an invalid format.`;
      }
    }

    if (rule.type === 'number') {
      const n = typeof value === 'number' ? value : Number(value);
      if (!Number.isFinite(n)) {
        errors[field] = `${label} must be a number.`;
      }
    }

    if (rule.email && !EMAIL_RE.test(String(value))) {
      errors[field] = `${label} must be a valid email address.`;
    }

    if (rule.array && !Array.isArray(value)) {
      errors[field] = `${label} must be an array.`;
    } else if (rule.array && rule.arrayOfStrings) {
      for (const item of value) {
        if (typeof item !== 'string') {
          errors[field] = `${label} must contain only strings.`;
          break;
        }
      }
    }

    if (rule.oneOf && value !== undefined && value !== '' && !rule.oneOf.includes(value)) {
      errors[field] = `${label} must be one of: ${rule.oneOf.join(', ')}.`;
    }
  }
  return errors;
}

export function hasErrors(errors) {
  return Object.keys(errors).length > 0;
}
