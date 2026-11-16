import React, { useEffect, useState } from 'react';
import { CalendarDays, Images, MapPin, X, ChevronLeft, ChevronRight } from 'lucide-react';

export default function ActivitiesPage() {
  const [albums, setAlbums] = useState([]);
  const [selected, setSelected] = useState(null);
  const [active, setActive] = useState(0);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const controller = new AbortController();
    fetch('/api/public/activities', { signal: controller.signal, cache: 'no-store' })
      .then(async (r) => { if (!r.ok) throw new Error('ไม่สามารถโหลดกิจกรรมได้'); return r.json(); })
      .then((res) => { if (res.success) setAlbums(res.data || []); else throw new Error('ไม่สามารถโหลดกิจกรรมได้'); })
      .catch((e) => { if (e.name !== 'AbortError') setError(e.message); })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, []);

  const open = async (id) => {
    try {
      setError('');
      const res = await fetch(`/api/public/activities/${id}`, { cache: 'no-store' });
      if (!res.ok) throw new Error('ไม่สามารถเปิดอัลบั้มได้');
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'ไม่สามารถเปิดอัลบั้มได้');
      setSelected(data.data);
      setActive(0);
    } catch (e) { setError(e.message); }
  };

  const photos = selected?.photos || [];
  return <main className="container section" style={{ minHeight: '70vh' }}>
    <div className="section-title-wrap">
      <span className="section-badge">RYCOOP ACTIVITY GALLERY</span>
      <h1 className="section-title">ภาพกิจกรรมสหกรณ์</h1>
      <p className="section-subtitle">รวมภาพกิจกรรม การประชุม การอบรม และกิจกรรมเพื่อสมาชิก</p>
    </div>
    {loading && <p role="status">กำลังโหลดอัลบั้มกิจกรรม…</p>}
    {error && <p role="alert" style={{ color: '#b91c1c' }}>{error}</p>}
    {!loading && !albums.length && <div className="surface-card" style={{ padding: 32, textAlign: 'center' }}>ยังไม่มีอัลบั้มกิจกรรมที่เผยแพร่</div>}
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,260px),1fr))', gap: 22 }}>
      {albums.map((album) => <article key={album.id} className="surface-card" style={{ overflow: 'hidden' }}>
        <button type="button" onClick={() => open(album.id)} aria-label={`เปิดอัลบั้ม ${album.title}`} style={{ display: 'block', width: '100%', padding: 0, border: 0, cursor: 'pointer', background: '#e2e8f0' }}>
          {album.cover_url ? <img src={album.cover_url} alt={album.title} loading="lazy" style={{ display: 'block', width: '100%', height: 220, objectFit: 'cover' }}/> : <div style={{ height: 220, display: 'grid', placeItems: 'center' }}><Images size={38}/></div>}
        </button>
        <div style={{ padding: 18 }}>
          <h2 style={{ fontSize: '1.12rem', marginBottom: 8 }}>{album.title}</h2>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12, color: '#64748b', fontSize: '.82rem' }}>
            {album.event_date && <span><CalendarDays size={14} style={{ verticalAlign: 'middle' }}/> {album.event_date}</span>}
            <span><Images size={14} style={{ verticalAlign: 'middle' }}/> {album.photo_count} ภาพ</span>
          </div>
          {album.location && <p style={{ fontSize: '.85rem', marginTop: 8 }}><MapPin size={14} style={{ verticalAlign: 'middle' }}/> {album.location}</p>}
          <button type="button" className="btn btn-outline" style={{ marginTop: 14 }} onClick={() => open(album.id)}>ดูภาพทั้งหมด</button>
        </div>
      </article>)}
    </div>
    {selected && <div role="dialog" aria-modal="true" aria-label={selected.album.title} style={{ position: 'fixed', inset: 0, zIndex: 2500, background: 'rgba(10,20,36,.96)', color: 'white', overflow: 'auto', padding: 20 }} onKeyDown={(e) => { if (e.key === 'Escape') setSelected(null); if (e.key === 'ArrowRight') setActive((i) => Math.min(i+1,photos.length-1)); if (e.key === 'ArrowLeft') setActive((i) => Math.max(0,i-1)); }}>
      <div style={{ maxWidth: 1100, margin: '0 auto' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 16 }}>
          <h2 style={{ color: 'white' }}>{selected.album.title}</h2>
          <button aria-label="ปิดอัลบั้ม" onClick={() => setSelected(null)} style={{ background: 'transparent', border: '1px solid white', color: 'white', borderRadius: 10, padding: 10 }}><X/></button>
        </div>
        {photos.length > 0 && <div style={{ textAlign: 'center', marginTop: 20 }}>
          <img src={photos[active]?.url} alt={photos[active]?.caption || `ภาพที่ ${active+1}`} style={{ width: '100%', maxHeight: '65vh', objectFit: 'contain' }}/>
          <p>{photos[active]?.caption || ''}</p>
          <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 20 }}>
            <button onClick={() => setActive((i) => Math.max(0,i-1))} disabled={active===0} aria-label="ภาพก่อนหน้า"><ChevronLeft/></button>
            <span>{active+1} / {photos.length}</span>
            <button onClick={() => setActive((i) => Math.min(photos.length-1,i+1))} disabled={active===photos.length-1} aria-label="ภาพถัดไป"><ChevronRight/></button>
          </div>
        </div>}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(100px,1fr))', gap: 10, marginTop: 20 }}>
          {photos.map((photo,i) => <button key={photo.id} type="button" onClick={() => setActive(i)} aria-label={`ดูภาพ ${i+1}`} aria-pressed={active===i} style={{ padding: 0, border: active===i?'3px solid #38bdf8':'3px solid transparent', borderRadius: 8, overflow: 'hidden' }}><img src={photo.url} alt="" loading="lazy" style={{ height: 84, width: '100%', objectFit: 'cover' }}/></button>)}
        </div>
      </div>
    </div>}
  </main>;
}
