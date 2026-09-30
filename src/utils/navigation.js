/** Return an internal application path; unsafe redirects fail closed. */
export function normalizeSameOriginRedirect(redirect, fallback = '/') {
  if (redirect === null || redirect === undefined) return fallback;

  const value = String(redirect).trim();
  if (!value || (!value.startsWith('/') && !/^https?:\/\//i.test(value))) return fallback;

  try {
    const origin = typeof window !== 'undefined' ? window.location.origin : 'https://local.invalid';
    const target = new URL(value, origin);
    if (target.origin !== origin || !['http:', 'https:'].includes(target.protocol)) return fallback;
    return `${target.pathname}${target.search}${target.hash}`;
  } catch {
    return fallback;
  }
}
