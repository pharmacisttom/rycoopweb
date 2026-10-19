import { assetUrl } from './environment';
/** Resolve stored media values without duplicating the uploads prefix. */
export const resolveMediaUrl = (value, fallback = '') => {
  if (value === null || value === undefined) return assetUrl(fallback);

  const path = String(value).trim().replaceAll('\\', '/');
  if (!path) return assetUrl(fallback);
  if (/^(?:\.?\/)?(?:assets|img|templates|fonts)\//.test(path)) return assetUrl(path);
  if (/^https?:\/\//i.test(path) || path.startsWith('/')) return path;
  if (path.startsWith('assets/') || path.startsWith('storage/uploads/')) return `/${path}`;

  return `/storage/uploads/${path.replace(/^\/+/, '')}`;
};
