import React, { createContext, useContext, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import DOMPurify from 'dompurify';
import { memberAdminApi } from '../../services/memberAdminApi';
import './DynamicLayout.css';

export const LayoutPreviewContext = createContext(null);
const PUBLIC = '/api/public/v1/layout';
let cached;
export function usePublishedLayout() {
  const [value, setValue] = useState(null);
  useEffect(() => {
    let live = true;
    if (import.meta.env.VITE_PUBLIC_STATIC_MODE === 'true') return undefined;
    cached ||= fetch(PUBLIC, { headers: { Accept: 'application/json' } }).then(r => { if (!r.ok) throw new Error('Layout unavailable'); return r.json(); }).then(r => r.data).catch(() => null);
    cached.then(data => { if (live) setValue(data); });
    const refresh = () => { cached = undefined; fetch(PUBLIC).then(r => r.json()).then(r => { if (live) setValue(r.data); }).catch(() => {}); };
    window.addEventListener('layout-published', refresh);
    return () => { live = false; window.removeEventListener('layout-published', refresh); };
  }, []);
  return value;
}

function safeContentUrl(value) {
  if (typeof value !== 'string' || /[\\\u0000-\u0020]/.test(value)) return undefined;
  return /^(?:https:\/\/|\/(?!\/))/.test(value) ? value : undefined;
}

function Display({ responsive, device, children }) {
  const area = useRef(null);
  const settings = responsive || {};
  const styles = Object.fromEntries(['desktop', 'tablet', 'mobile'].map(d => [`--dl-${d}`, settings[d]?.columns || (d === 'desktop' ? 3 : d === 'tablet' ? 2 : 1)]));
  const mode = device ? settings[device]?.display : null;
  return <div className={`dl-display ${device ? `dl-device-${device}` : ''}`} style={styles}>
    <div className={`dl-items ${mode ? `dl-mode-${mode}` : ''}`} data-desktop={settings.desktop?.display || 'grid'} data-tablet={settings.tablet?.display || 'grid'} data-mobile={settings.mobile?.display || 'grid'} ref={area} tabIndex={0} aria-label="รายการเนื้อหา เลื่อนด้วยแป้นลูกศร">{children}</div>
    {(mode === 'carousel' || !device && Object.values(settings).some(v => v.display === 'carousel')) && <div className="dl-carousel-controls"><button type="button" onClick={() => area.current?.scrollBy({ left: -320, behavior: 'smooth' })}>← ก่อนหน้า</button><button type="button" onClick={() => area.current?.scrollBy({ left: 320, behavior: 'smooth' })}>ถัดไป →</button></div>}
  </div>;
}

export function ActivityGallery({ category, preview = false, responsive, device }) {
  const [page, setPage] = useState(1), [result, setResult] = useState(null), [error, setError] = useState('');
  useEffect(() => { setPage(1); }, [category]);
  useEffect(() => {
    const abort = new AbortController(); setError('');
    const path = preview ? `/api/admin/v1/layout/gallery/albums?page=${page}&preview=1${category ? `&category=${encodeURIComponent(category)}` : ''}` : `${PUBLIC}/albums?page=${page}${category ? `&category=${encodeURIComponent(category)}` : ''}`;
    const read = preview ? memberAdminApi(path, { signal: abort.signal }) : fetch(path, { signal: abort.signal }).then(async r => { const body = await r.json(); if (!r.ok) throw new Error(body.message); return body; });
    read.then(r => setResult(r.data)).catch(e => { if (e.name !== 'AbortError') setError(e.message || 'โหลดกิจกรรมไม่สำเร็จ'); });
    return () => abort.abort();
  }, [page, category, preview]);
  if (error) return <p role="alert">{error}</p>;
  return <><Display responsive={responsive} device={device}>{(result?.items || []).map(a => {
    const image = a.images.find(i => i.id === a.cover_id) || a.images[0];
    return <article className="dl-card" key={a.id}>{image && <img src={image.thumbnail} alt={image.alt} loading="lazy" />}<h3>{preview ? a.title : <Link to={`/gallery/${a.slug}`}>{a.title}</Link>}</h3><time dateTime={a.activity_date}>{a.activity_date}</time><p>{a.description}</p>{preview && a.archived && <span>เก็บถาวร</span>}</article>;
  })}</Display>{result?.total === 0 && <p>ยังไม่มีอัลบั้มกิจกรรมที่เผยแพร่</p>}<div className="dl-pagination"><button type="button" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>หน้าก่อน</button><span>หน้า {page} / {Math.max(1, Math.ceil((result?.total || 0) / 20))}</span><button type="button" disabled={!result || page * 20 >= result.total} onClick={() => setPage(p => p + 1)}>หน้าถัดไป</button></div></>;
}

function NewsFeed({ responsive, device }) {
  const [news, setNews] = useState([]);
  useEffect(() => { const abort = new AbortController(); fetch('/api/public/news', { signal: abort.signal }).then(r => r.json()).then(r => setNews((r.data || []).slice(0, 6))).catch(() => {}); return () => abort.abort(); }, []);
  return <Display responsive={responsive} device={device}>{news.map(n => <article className="dl-card" key={n.id}><h3><Link to="/news">{n.title}</Link></h3><p>{n.excerpt || n.summary}</p></article>)}</Display>;
}

export function DynamicSections({ state, legacy = {}, preview = false, device, rootId = null, home = false }) {
  const nodes = state?.sections || [];
  const render = (node, depth = 0) => {
    if (depth > 8 || !node.visible || node.archived || home && !node.home) return null;
    const config = node.config || {};
    const children = nodes.filter(n => n.parent_id === node.id);
    let content;
    if (node.type === 'feature') content = legacy[config.feature] || <p>{preview ? `ฟีเจอร์เดิม: ${config.feature} (แสดงข้อมูลจริงบนหน้าแรก)` : <Link to={`/#section-${node.slug}`}>ดู {node.title} บนหน้าแรก →</Link>}</p>;
    else if (node.type === 'gallery') content = <ActivityGallery category={config.category_id} preview={preview} responsive={node.responsive} device={device} />;
    else if (node.type === 'news') content = <NewsFeed responsive={node.responsive} device={device} />;
    else if (node.type === 'rich_text') content = <div className="dl-rich" dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(config.html || '', { USE_PROFILES: { html: true } }) }} />;
    else content = <Display responsive={node.responsive} device={device}>{(config.items?.length ? config.items : [config]).map((item, i) => <article className={`dl-card dl-type-${node.type}`} key={i}>{item.image && <img src={safeContentUrl(item.image)} alt={item.title || node.title} loading="lazy" />}{item.title && <h3>{item.title}</h3>}{item.description && <p>{item.description}</p>}{item.href && <a href={safeContentUrl(item.href)}>ดูรายละเอียด <span className="sr-only">{item.title || node.title}</span> →</a>}</article>)}</Display>;
    return <section key={node.id} id={`section-${node.slug}`} className={`dl-section ${node.type === 'feature' ? 'dl-existing' : ''}`} data-section={node.slug}>{node.type !== 'feature' && <h2>{node.title}</h2>}{content}{children.map(n => render(n, depth + 1))}</section>;
  };
  return <div className="dl-renderer">{rootId ? nodes.filter(n => n.id === rootId).map(n => render(n)) : nodes.filter(n => !n.parent_id).map(n => render(n))}</div>;
}

export function DynamicHome({ legacy }) {
  const published = usePublishedLayout(), draft = useContext(LayoutPreviewContext);
  if (draft) return <DynamicSections state={draft} legacy={legacy} preview home />;
  return published ? <DynamicSections state={published.state} legacy={legacy} home /> : <div>{Object.entries(legacy).map(([key, element]) => <React.Fragment key={key}>{element}</React.Fragment>)}</div>;
}
export function DynamicNavigation() {
  const published = usePublishedLayout();
  const nodes = published?.state.sections.filter(n => n.navigation) || [];
  return nodes.length ? <nav className="dl-navigation container" aria-label="เมนูเนื้อหาเพิ่มเติม">{nodes.map(n => <Link key={n.id} to={`/sections/${n.slug}`}>{n.title}</Link>)}</nav> : null;
}
function useMetadata(title, description) {
  useEffect(() => {
    if (!title) return undefined;
    const original = document.title; document.title = `${title} | สหกรณ์ออมทรัพย์สาธารณสุขระยอง`;
    const selectors = ['meta[name="description"]', 'link[rel="canonical"]'];
    const saved = selectors.map((selector, i) => {
      let node = document.head.querySelector(selector); const created = !node;
      if (!node) { node = document.createElement(i ? 'link' : 'meta'); node.setAttribute(i ? 'rel' : 'name', i ? 'canonical' : 'description'); document.head.appendChild(node); }
      const attr = i ? 'href' : 'content', value = node.getAttribute(attr);
      node.setAttribute(attr, i ? window.location.origin + window.location.pathname : description || title);
      return () => { if (created) node.remove(); else if (value === null) node.removeAttribute(attr); else node.setAttribute(attr, value); };
    });
    return () => { document.title = original; saved.forEach(restore => restore()); };
  }, [title, description]);
}
export function DynamicSectionPage() {
  const { slug } = useParams(), pub = usePublishedLayout();
  const section = pub?.state.sections.find(n => n.slug === slug);
  useMetadata(section?.title, section?.config.description);
  return <main className="container section">{section ? <DynamicSections state={pub.state} rootId={section.id} /> : <h1>ไม่พบเนื้อหาที่เผยแพร่</h1>}</main>;
}
export function ActivityAlbumPage() {
  const { slug } = useParams(), [album, setAlbum] = useState(null), [error, setError] = useState(''), [active, setActive] = useState(null);
  const dialog = useRef(null);
  useEffect(() => { const abort = new AbortController(); setAlbum(null); setError(''); fetch(`${PUBLIC}/albums/${encodeURIComponent(slug)}`, { signal: abort.signal }).then(async r => { const body = await r.json(); if (!r.ok) throw new Error(body.message); return body; }).then(r => setAlbum(r.data)).catch(e => { if (e.name !== 'AbortError') setError(e.message); }); return () => abort.abort(); }, [slug]);
  useMetadata(album?.seo_title || album?.title, album?.seo_description || album?.description);
  useEffect(() => { if (active !== null && !dialog.current.open) dialog.current.showModal(); }, [active]);
  const move = delta => setActive(i => (i + delta + album.images.length) % album.images.length);
  const responsive = Object.fromEntries(['desktop', 'tablet', 'mobile'].map((d, i) => [d, { display: album?.display || 'grid', columns: 3 - i }]));
  return <main className="container section">{error && <p role="alert">{error}</p>}{album && <><Link to="/gallery">← กิจกรรมทั้งหมด</Link><h1>{album.title}</h1><time dateTime={album.activity_date}>{album.activity_date}</time><p>{album.description}</p><Display responsive={responsive}>{album.images.map((image, i) => <figure className="dl-card" key={image.id}><button type="button" onClick={() => setActive(i)} aria-label={`ขยายภาพ ${image.alt}`}><img src={image.thumbnail} alt={image.alt} loading="lazy" /></button><figcaption>{image.caption}</figcaption></figure>)}</Display><dialog ref={dialog} aria-label="ภาพกิจกรรมขนาดใหญ่" onClose={() => setActive(null)} onKeyDown={e => { if (e.key === 'ArrowLeft') move(-1); if (e.key === 'ArrowRight') move(1); }}><button type="button" onClick={() => dialog.current.close()}>ปิด (Esc)</button>{active !== null && <><img className="dl-lightbox" src={album.images[active]?.src} alt={album.images[active]?.alt} /><p>{album.images[active]?.caption}</p></>}<button type="button" onClick={() => move(-1)}>← ภาพก่อน</button><button type="button" onClick={() => move(1)}>ภาพถัดไป →</button></dialog></>}</main>;
}
export function ActivityIndexPage() { const pub = usePublishedLayout(), [category, setCategory] = useState(''); useMetadata('รูปกิจกรรมของสหกรณ์', 'อัลบั้มและรูปกิจกรรมของสหกรณ์'); return <main className="container section"><h1>รูปกิจกรรมของสหกรณ์</h1><label>หมวดกิจกรรม <select value={category} onChange={e => setCategory(e.target.value)}><option value="">ทุกหมวด</option>{pub?.state.categories.map(c => <option key={c.id} value={c.id}>{c.parent_id ? '↳ ' : ''}{c.title}</option>)}</select></label><ActivityGallery category={category || undefined} /></main>; }
