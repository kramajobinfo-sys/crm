// Helpers for admin-defined custom fields shared across CRM entity pages.

// Build a custom_fields object from the definitions, overlaying any stored values.
export function seedCustomFields(fields, existing = {}) {
  const out = {};
  for (const cf of (fields || [])) {
    const v = existing?.[cf.key];
    out[cf.key] = v !== undefined && v !== null ? v : (cf.type === 'checkbox' ? false : '');
  }
  return out;
}

// Drop blank optionals so the server doesn't coerce '' → 0 for numbers, etc.
// Required-but-empty fields are kept so the server returns a validation error.
export function stripBlankCustomFields(obj) {
  if (!obj) return obj;
  return Object.fromEntries(
    Object.entries(obj).filter(([, v]) => v !== '' && v !== null && v !== undefined));
}
