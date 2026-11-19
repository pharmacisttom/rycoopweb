import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronLeft, ChevronRight, Pause, Play, X, Maximize2 } from 'lucide-react';
import './DynamicSlideshow.css';

/** Accessible, published-only carousel. Images originate from the validated CMS tree. */
export default function DynamicSlideshow({ slides = [], title = 'ภาพประชาสัมพันธ์', interval = 6 }) {
  const items = Array.isArray(slides) ? slides.filter(s => s && typeof s.image === 'string' && s.image.startsWith('/')) : [];
  const [index, setIndex] = useState(0);
  const [playing, setPlaying] = useState(false);
  const [expanded, setExpanded] = useState(false);
  const [reduceMotion, setReduceMotion] = useState(true);
  const closeRef = useRef(null);
  const previousFocus = useRef(null);
  const count = items.length;
  useEffect(() => {
    const media = window.matchMedia('(prefers-reduced-motion: reduce)');
    const sync = () => { setReduceMotion(media.matches); if (media.matches) setPlaying(false); };
    sync(); media.addEventListener('change', sync);
    return () => media.removeEventListener('change', sync);
  }, []);
  useEffect(() => { setIndex(0); setPlaying(false); }, [slides]);
  useEffect(() => {
    if (!playing || expanded || reduceMotion || count < 2) return undefined;
    const timer = window.setInterval(() => setIndex(current => (current + 1) % count), Math.max(4, Math.min(12, interval)) * 1000);
    return () => window.clearInterval(timer);
  }, [playing, expanded, reduceMotion, count, interval]);
  useEffect(() => {
    if (!expanded) return undefined;
    previousFocus.current = document.activeElement;
    const overflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    closeRef.current?.focus();
    const keydown = e => {
      if (e.key === 'Escape') setExpanded(false);
      else if (e.key === 'ArrowRight') setIndex(i => (i + 1) % count);
      else if (e.key === 'ArrowLeft') setIndex(i => (i - 1 + count) % count);
      else if (e.key === 'Tab') {
        const controls = [...document.querySelectorAll('.dynamic-slide-modal button:not([disabled]),.dynamic-slide-modal a[href]')];
        const first = controls[0], last = controls[controls.length - 1];
        if (!first) return;
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    };
    document.addEventListener('keydown', keydown);
    return () => { document.removeEventListener('keydown', keydown); document.body.style.overflow = overflow; previousFocus.current?.focus?.(); };
  }, [expanded, count]);
  if (!count) return <p role="status">ยังไม่มีรูปภาพสไลด์ที่เผยแพร่</p>;
  const current = items[Math.min(index, count - 1)] || items[0];
  const move = delta => { setPlaying(false); setIndex(i => (i + delta + count) % count); };
  const slideContent = (modal = false) => <div className={'dynamic-slide-stage' + (modal ? ' dynamic-slide-stage-large' : '')}>
    <img key={current.image} src={current.image} alt={current.title || title} draggable="false"
      style={{ objectFit: current.fit === 'contain' ? 'contain' : 'cover' }} />
    <div className="dynamic-slide-gradient" />
    {(current.title || current.caption || (current.button_text && current.button_link)) && <div className="dynamic-slide-copy">
      {current.title && <h3>{current.title}</h3>}
      {current.caption && <p>{current.caption}</p>}
      {current.button_text && current.button_link && <Link className="dynamic-slide-cta" to={current.button_link} onClick={() => setExpanded(false)}>
        {current.button_text}
      </Link>}
    </div>}
  </div>;
  const controls = <div className="dynamic-slide-controls">
    <button type="button" aria-label="สไลด์ก่อนหน้า" onClick={() => move(-1)} disabled={count < 2}><ChevronLeft size={22} /></button>
    <span aria-live="polite">{Math.min(index, count - 1) + 1} / {count}</span>
    <button type="button" aria-label="สไลด์ถัดไป" onClick={() => move(1)} disabled={count < 2}><ChevronRight size={22} /></button>
  </div>;
  return <div className="dynamic-slideshow" aria-label={title} aria-roledescription="สไลด์โชว์">
    {slideContent()}
    <div className="dynamic-slide-toolbar">
      {controls}
      <div className="dynamic-slide-actions">
        <button type="button" onClick={() => setPlaying(v => !v)} disabled={reduceMotion || count < 2}
          aria-label={playing ? 'หยุดเลื่อนสไลด์' : 'เล่นสไลด์อัตโนมัติ'}>
          {playing ? <Pause size={18} /> : <Play size={18} />}
        </button>
        <button type="button" onClick={() => { setPlaying(false); setExpanded(true); }} aria-label="ขยายสไลด์เต็มหน้าจอ"><Maximize2 size={18} /> ขยายภาพ</button>
      </div>
    </div>
    {count > 1 && <div className="dynamic-slide-dots" aria-label="เลือกสไลด์">
      {items.map((slide, i) => <button type="button" key={i} aria-label={'สไลด์ที่ ' + (i + 1)} aria-current={index === i}
        onClick={() => { setIndex(i); setPlaying(false); }} />)}
    </div>}
    {expanded && <div className="dynamic-slide-backdrop" onMouseDown={e => { if (e.target === e.currentTarget) setExpanded(false); }}>
      <div className="dynamic-slide-modal" role="dialog" aria-modal="true" aria-label={title + ' ภาพขยาย'}>
        <button ref={closeRef} className="dynamic-slide-close" type="button" onClick={() => setExpanded(false)} aria-label="ปิดภาพขยาย"><X /></button>
        {slideContent(true)}
        {controls}
      </div>
    </div>}
  </div>;
}
