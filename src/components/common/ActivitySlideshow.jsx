import React, { useEffect, useRef, useState } from 'react';
import SafeImage from './SafeImage';
import './ActivitySlideshow.css';

export default function ActivitySlideshow({ images = [], title = 'ภาพกิจกรรม' }) {
  const [index, setIndex] = useState(0);
  const [playing, setPlaying] = useState(false);
  const [failed, setFailed] = useState(false);
  const [zoom, setZoom] = useState(1);
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
  useEffect(() => { setFailed(false); setZoom(1); }, [image?.src]);
  useEffect(() => {
    if (!playing || reduced || images.length < 2) return undefined;
    const timer = window.setInterval(() => setIndex(current => (current + 1) % images.length), 5000);
    const stop = () => { if (document.hidden) setPlaying(false); };
    document.addEventListener('visibilitychange', stop);
    return () => { window.clearInterval(timer); document.removeEventListener('visibilitychange', stop); };
  }, [playing, reduced, images.length]);
  function move(delta) { setPlaying(false); setZoom(1); setIndex(current => (current + delta + images.length) % images.length); }
  function openReader() { setPlaying(false); setZoom(1); if(lightbox.current && !lightbox.current.open) lightbox.current.showModal(); }
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
      {failed ? <p role="status">โหลดภาพนี้ไม่ได้ กรุณาลองภาพถัดไป</p> : <button type="button" className="activity-stage-open" onClick={openReader} aria-label="แตะเพื่ออ่านภาพขนาดใหญ่"><img src={image.src} alt={image.alt || title} draggable="false" onError={() => setFailed(true)} /></button>}
    </div>
    {image.caption && <p className="activity-caption">{image.caption}</p>}
    {controls}
    <div className="activity-controls">
      <button type="button" disabled={images.length < 2 || reduced} onClick={() => setPlaying(value => !value)}>{playing ? 'หยุดสไลด์' : 'เล่นสไลด์'}</button>
      <button type="button" onClick={openReader}>เปิดภาพเต็มจอ / ซูมอ่าน</button>
    </div>
    <div className="activity-thumbnails" aria-label="เลือกภาพ">
      {images.map((item, position) => <button type="button" key={`${item.src}-${position}`} aria-label={`ภาพที่ ${position + 1}`} aria-pressed={index === position}
        onClick={() => { setPlaying(false); setIndex(position); }}>
        <SafeImage src={item.thumbnail || item.src} alt="" loading="lazy" />
      </button>)}
    </div>
    <dialog ref={lightbox} className="activity-lightbox" aria-label={`${title} ภาพขยาย`} onKeyDown={event=>{
      if(event.key==='+' || event.key==='='){event.preventDefault();setZoom(v=>Math.min(4,Number((v+.25).toFixed(2))));}
      else if(event.key==='-'){event.preventDefault();setZoom(v=>Math.max(1,Number((v-.25).toFixed(2))));}
      else keyboard(event);
    }} onCancel={event => { event.stopPropagation(); }} onClick={event => { if (event.target === event.currentTarget) lightbox.current.close(); }}>
      <div className="activity-reader-toolbar">
        <span className="activity-reader-title">อ่านภาพเอกสาร · {index+1}/{images.length}</span>
        <div className="activity-reader-actions">
          <button type="button" onClick={()=>setZoom(v=>Math.max(1,Number((v-.25).toFixed(2))))} disabled={zoom<=1} aria-label="ย่อภาพ">−</button>
          <output aria-label="ระดับการซูม">{zoom===0?'ทั้งหน้า':`${Math.round(zoom*100)}%`}</output>
          <button type="button" onClick={()=>setZoom(v=>Math.min(4,Number((v+.25).toFixed(2))))} disabled={zoom>=4} aria-label="ขยายภาพ">+</button>
          <button type="button" onClick={()=>setZoom(1)}>พอดีความกว้าง</button>
          <button type="button" onClick={()=>setZoom(0)}>เห็นทั้งหน้า</button>
          <a className="activity-reader-original" href={image.src} target="_blank" rel="noopener noreferrer">เปิดภาพต้นฉบับ</a>
          <button type="button" className="activity-close" autoFocus onClick={() => lightbox.current.close()}>ปิด (Esc)</button>
        </div>
      </div>
      <div className="activity-reader-viewport" role="region" aria-label="ภาพเอกสาร เลื่อนเพื่ออ่านรายละเอียด" tabIndex={0}>
        <img src={image.src} alt={image.alt || title} style={zoom===0?{width:'auto',height:'auto',maxWidth:'100%',maxHeight:'100%',objectFit:'contain',margin:'auto'}:{width:`${zoom*100}%`,height:'auto',maxWidth:'none',maxHeight:'none',objectFit:'unset',margin:'0'}} />
      </div>
      {image.caption && <p className="activity-reader-caption">{image.caption}</p>}
      {images.length > 1 && controls}
      <p className="activity-reader-help">เริ่มที่ขนาดพอดีความกว้างเพื่อให้อ่านตัวอักษรได้ชัด · เลื่อนขึ้นลงเพื่ออ่านเอกสารทั้งหน้า · ซูมได้สูงสุด 400% · Esc เพื่อปิด</p>
    </dialog>
  </section>;
}
