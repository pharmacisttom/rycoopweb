/** Resolve stored media values without duplicating the uploads prefix. */
export const resolveMediaUrl = (value, fallback = '') => {
  if (value === null || value === undefined) return fallback;

  const path = String(value).trim().replaceAll('\\', '/');
  if (!path) return fallback;
  // A new, client-side image preview is a data URI, not a storage path.
  // Only the three image types supported by News Gallery are allowed here.
  if (/^data:image\/(?:jpeg|png|webp);base64,[a-z0-9+/=]+$/i.test(path)) return path;
  if (/^https?:\/\//i.test(path) || path.startsWith('/')) return path;
  if (path.startsWith('assets/') || path.startsWith('storage/uploads/')) return `/${path}`;

  return `/storage/uploads/${path.replace(/^\/+/, '')}`;
};
