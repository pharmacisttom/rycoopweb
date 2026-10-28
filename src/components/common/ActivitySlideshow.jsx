import React, { useEffect, useRef, useState } from 'react';
import './ActivitySlideshow.css';

export default function ActivitySlideshow({ images = [], title = 'ภาพกิจกรรม' }) {
  const [index, setIndex] = useState(0);
  const [playing, setPlaying] = useState(false);
  const [failed, setFailed] = useState(false);
  const [reduced, setReduced] = useState(true);
  const lightbox = useRef(null);
  const pointer = useRef(null);
  const image = images[index] || images[0];
  useEffect(() => {
    const media = window.matchMedia('(prefers-reduced-motion: reduce)');
    const update = () => { setReduced(media.matches); if (media.matches) setPlaying(false); };
    update(); media.addEventListener('change', update);
    return () => media.removeEventListener('change', update);
  }, []);
  useEffect(() => { setIndex(0); setPlaying(false); }, [images]);
  useEffect(() => { setFailed(false); }, [image?.src]);
  useEffect(() => {
    if (!playing || reduced || images.length < 2) return undefined;
    const timer = window.setInterval(() => setIndex(current => (current + 1) % images.length), 5000);
    const stop = () => { if (document.hidden) setPlaying(false); };
    document.addEventListener('visibilitychange', stop);
    return () => { window.clearInterval(timer); document.removeEventListener('visibilitychange', stop); };
  }, [playing, reduced, images.length]);
  function move(delta) { setPlaying(false); setIndex(current => (current + delta + images.length) % images.length); }
  function keyboard(event) {
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
      event.preventDefault(); event.stopPropagation(); move(event.key === 'ArrowRight' ? 1 : -1);
    }
  }
  if (!image) return null;
  const controls = <div className="activity-controls">
    <button type="button" onClick={() => move(-1)} disabled={images.length < 2} aria-label="ภาพก่อนหน้า">←</button>
    <span aria-live={playing ? 'off' : 'polite'}>{index + 1} / {images.length}</span>
    <button type="button" onClick={() => move(1)} disabled={images.length < 2} aria-label="ภาพถัดไป">→</button>
  </div>;
  return <section className="activity-gallery" aria-label={title} aria-roledescription="สไลด์โชว์" onKeyDown={keyboard}>
    <div className="activity-stage" tabIndex={0} aria-label="ใช้ลูกศรซ้ายขวาเพื่อเปลี่ยนภาพ"
      onFocus={() => setPlaying(false)} onPointerDown={event => {
        setPlaying(false); pointer.current = { x: event.clientX, y: event.clientY, id: event.pointerId };
        event.currentTarget.setPointerCapture?.(event.pointerId);
      }} onPointerCancel={() => { pointer.current = null; }} onPointerUp={event => {
        const start = pointer.current; pointer.current = null;
        if (!start || start.id !== event.pointerId) return;
        const dx = event.clientX - start.x; const dy = event.clientY - start.y;
        if (Math.abs(dx) > 48 && Math.abs(dx) > Math.abs(dy)) move(dx < 0 ? 1 : -1);
      }}>
      {failed ? <p role="status">โหลดภาพนี้ไม่ได้ กรุณาลองภาพถัดไป</p> : <img src={image.src} alt={image.alt || title} draggable="false" onError={() => setFailed(true)} />}
    </div>
    {image.caption && <p className="activity-caption">{image.caption}</p>}
    {controls}
    <div className="activity-controls">
      <button type="button" disabled={images.length < 2 || reduced} onClick={() => setPlaying(value => !value)}>{playing ? 'หยุดสไลด์' : 'เล่นสไลด์'}</button>
      <button type="button" onClick={() => { setPlaying(false); lightbox.current.showModal(); }}>ขยายภาพ</button>
    </div>
    <div className="activity-thumbnails" aria-label="เลือกภาพ">
      {images.map((item, position) => <button type="button" key={`${item.src}-${position}`} aria-label={`ภาพที่ ${position + 1}`} aria-pressed={index === position}
        onClick={() => { setPlaying(false); setIndex(position); }}>
        <img src={item.thumbnail || item.src} alt="" loading="lazy" />
      </button>)}
    </div>
    <dialog ref={lightbox} className="activity-lightbox" aria-label={`${title} ภาพขยาย`} onKeyDown={keyboard}
      onCancel={event => { event.stopPropagation(); }} onClick={event => { if (event.target === event.currentTarget) lightbox.current.close(); }}>
      <button type="button" className="activity-close" autoFocus onClick={() => lightbox.current.close()}>ปิดภาพขยาย (Esc)</button>
      <img src={image.src} alt={image.alt || title} />
      {image.caption && <p>{image.caption}</p>}{controls}
    </dialog>
  </section>;
}
