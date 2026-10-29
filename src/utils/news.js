import { resolveMediaUrl } from './media';

const DEFAULT_IMAGE = '/assets/img/news_placeholder.jpg';

export const formatNewsDate = (value) => {
  if (!value) return '';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);

  return new Intl.DateTimeFormat('th-TH', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(date);
};

export function normalizeGallery(value) {
  if (typeof value === 'string') { try { value = JSON.parse(value); } catch { return []; } }
  if (!Array.isArray(value)) return [];
  return value.map(item => typeof item === 'string' ? { src: item } : item).filter(item => item && typeof item.src === 'string' && !/^\s*(?:javascript|data|vbscript):/i.test(item.src))
    .map(item => ({ src: resolveMediaUrl(item.src), thumbnail: resolveMediaUrl(item.thumbnail || item.src), alt: String(item.alt || ''), caption: String(item.caption || '') }));
}

/** Normalizes PHP API and admin payloads to the public news-card shape. */
export const normalizeNewsItem = (item) => ({
  ...item,
  gallery_images: normalizeGallery(item.gallery_images),
  category: item.category || item.category_name || 'ข่าวสาร',
  date: formatNewsDate(item.date || item.publish_at),
  excerpt: item.excerpt || item.summary || '',
  image: resolveMediaUrl(item.image || item.cover_image, DEFAULT_IMAGE),
  views: Number(item.views ?? item.views_count ?? 0),
});
