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

/** Normalizes PHP API and admin payloads to the public news-card shape. */
export const normalizeNewsItem = (item) => ({
  ...item,
  category: item.category || item.category_name || 'ข่าวสาร',
  date: formatNewsDate(item.date || item.publish_at),
  excerpt: item.excerpt || item.summary || '',
  image: resolveMediaUrl(item.image || item.cover_image, DEFAULT_IMAGE),
  views: Number(item.views ?? item.views_count ?? 0),
});
