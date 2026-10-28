// Synthetic component fixture; never mounts a protected admin route or calls an API.
import React from 'react';
import { createRoot } from 'react-dom/client';
import ActivitySlideshow from '../../src/components/common/ActivitySlideshow';
const images = ['#1d4ed8', '#0f766e', '#102443'].map((color, index) => ({
  src: `data:image/svg+xml,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800"><rect width="1200" height="800" fill="${color}"/><text x="100" y="400" font-size="80" fill="white">Synthetic activity ${index + 1}</text></svg>`)}`,
  alt: `ภาพทดสอบ ${index + 1}`, caption: `คำบรรยายทดสอบ ${index + 1}`,
}));
createRoot(document.getElementById('root')).render(<main style={{ maxWidth: 1000, margin: 'auto', fontFamily: 'sans-serif' }}>
  <h1>อัลบั้มภาพกิจกรรม — ข้อมูลทดสอบ</h1><ActivitySlideshow images={images} title="อัลบั้มทดสอบ" />
</main>);
