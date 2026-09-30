import React, { useEffect, useState } from 'react';
import { resolveMediaUrl } from '../../utils/media';

export default function SafeImage({ src, fallback = '/assets/img/news_placeholder.jpg', alt = '', loading = 'lazy', ...props }) {
  const primary = resolveMediaUrl(src, fallback);
  const fallbackUrl = resolveMediaUrl(fallback, '');
  const [currentSrc, setCurrentSrc] = useState(primary);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    setCurrentSrc(primary);
    setFailed(false);
  }, [primary]);

  const handleError = () => {
    if (!failed && fallbackUrl && currentSrc !== fallbackUrl) {
      setFailed(true);
      setCurrentSrc(fallbackUrl);
    }
  };

  if (!currentSrc) return null;
  return <img {...props} src={currentSrc} alt={alt} loading={loading} decoding="async" onError={handleError} />;
}
